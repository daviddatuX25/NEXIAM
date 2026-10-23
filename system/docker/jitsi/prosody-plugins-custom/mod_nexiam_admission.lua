local http = require "net.http";
local async = require "util.async";
local jid = require "util.jid";
local st = require "util.stanza";
local cjson = require "cjson.safe";
local basexx = require "basexx";
local usermanager = require "core.usermanager";
local jitsi_util = module:require "util";

local admission_url = module:get_option_string("nexiam_admission_url");
local presence_url = module:get_option_string("nexiam_presence_url");
local admission_secret = module:get_option_string("nexiam_admission_secret");
local callback_timeout = module:get_option_number("nexiam_admission_timeout", 3);
local main_muc_host = module:get_option_string("muc_mapper_domain_base");
local shared = module:shared("/nexiam_admission");
shared.fenced_rooms = shared.fenced_rooms or {};
shared.pending_joins = shared.pending_joins or {};
shared.active_joins = shared.active_joins or {};
shared.terminating_rooms = shared.terminating_rooms or {};

if not admission_url or admission_url == "" or not presence_url or presence_url == ""
    or not admission_secret or #admission_secret < 32 or not main_muc_host then
    module:log("error", "Required NEXIAM admission, presence, or lobby configuration is missing or too short");
    return;
end

local muc_component = module.host;


local function room_key(room_name)
    if type(room_name) ~= "string" or #room_name < 1 or #room_name > 200
        or not room_name:match("^[a-z0-9][a-z0-9_-]*$") then
        return nil;
    end
    return room_name .. "@" .. muc_component;
end

local function deny(event, reason)
    event.origin.send(st.error_reply(event.stanza, "auth", "not-authorized", reason));
    return true;
end

local function pending_join_key(room_jid, bare_jid)
    return room_jid .. "\0" .. bare_jid;
end

local function decode_schedule_generation(token)
    local payload = token:match("^[^.]+%.([^.]+)%.[^.]+$");
    if not payload then
        return nil;
    end
    local ok, raw_claims = pcall(basexx.from_url64, payload);
    if not ok or type(raw_claims) ~= "string" then
        return nil;
    end
    local claims = cjson.decode(raw_claims);
    local generation = type(claims) == "table" and claims.schedule_generation or nil;
    if type(generation) ~= "number" or generation < 1 or generation % 1 ~= 0 then
        return nil;
    end
    return generation;
end

local function request_with_deadline(url, body)
    local wait, done = async.waiter();
    local settled = false;
    local response;
    local request;
    local timeout = module:add_timer(callback_timeout, function()
        if settled then
            return;
        end
        settled = true;
        if request and request.cancel then
            request:cancel();
        end
        done();
    end);

    request = http.request(url, {
        method = "POST";
        body = body;
        headers = {
            ["Content-Type"] = "application/json";
            ["Accept"] = "application/json";
            ["X-Jitsi-Admission-Secret"] = admission_secret;
        };
    }, function(response_body, code)
        if settled then
            return;
        end
        settled = true;
        timeout:stop();
        response = { body = response_body; code = code; };
        done();
    end);
    wait();
    return response;
end

local function request_callback(room_name, participant_id, token)
    local body = cjson.encode({
        room_name = room_name;
        participant_id = participant_id;
        token = token;
    });
    if not body then
        return false;
    end

    local response = request_with_deadline(admission_url, body);
    if not response or response.code == 0 then
        module:log("warn", "NEXIAM admission callback unavailable");
        return false;
    end
    local decoded = cjson.decode(response.body or "");
    local allowed = response.code == 200 and type(decoded) == "table" and decoded.allowed == true;
    if not allowed then
        module:log("warn", "NEXIAM admission callback rejected a consultation join");
    end
    return allowed;
end

local function authorize_join(event)
    local stanza = event.stanza;
    local room = event.room;
    local session = event.origin;
    if not stanza or not room or not session or stanza.name ~= "presence" or stanza.attr.type == "unavailable" then
        return;
    end

    local from = stanza.attr.from;
    if jitsi_util.is_healthcheck_room(room.jid) or usermanager.is_admin(from) then
        return;
    end

    local bare_jid = from and jid.bare(from);
    local node = bare_jid and jid.node(bare_jid);
    local context_user = session.jitsi_meet_context_user;
    local token = session.auth_token;
    local room_name = jid.node(room.jid);
    local full_room = room_key(room_name);
    local participant_id = context_user and context_user.id;

    if not full_room or room.jid ~= full_room or not node or type(participant_id) ~= "string"
        or participant_id == "" or type(token) ~= "string" or token == "" then
        module:log("warn", "Denying room join with missing or malformed verified token context");
        return deny(event, "Consultation access could not be verified");
    end

    local generation = decode_schedule_generation(token);
    if not generation then
        return deny(event, "Consultation schedule generation could not be verified");
    end
    local fenced_generation = shared.fenced_rooms[full_room];
    if fenced_generation and generation <= fenced_generation then
        module:log("info", "Denying join to fenced consultation room");
        return deny(event, "Consultation access has been closed");
    end

    if not request_callback(room_name, participant_id, token) then
        module:log("warn", "NEXIAM denied room admission");
        return deny(event, "Consultation admission was denied");
    end


    local affiliation = context_user.affiliation;
    local moderator = context_user.moderator;
    local is_host = (moderator == true or moderator == "true") and affiliation == "owner";
    local is_applicant = moderator == false and affiliation == "member";
    if not is_host and not is_applicant then
        module:log("warn", "Denying room join with invalid consultation role claims");
        return deny(event, "Consultation role could not be verified");
    end
    if not room:get_members_only() then
        return deny(event, "Consultation lobby is not enforced");
    end
    if is_applicant and room:get_affiliation(bare_jid) == "owner" then
        return deny(event, "Applicant cannot join as a consultation host");
    end
    if is_applicant and room:get_affiliation(bare_jid) ~= "member" then
        shared.pending_joins[pending_join_key(full_room, bare_jid)] = nil;
        if fenced_generation then
            shared.fenced_rooms[full_room] = nil;
        end
        return;
    end
    if fenced_generation then
        shared.fenced_rooms[full_room] = nil;
    end
    if is_host then
        local updated = room:set_affiliation(true, bare_jid, "owner");
        if not updated then
            return deny(event, "Consultation host role could not be applied");
        end
        event.occupant.role = "moderator";
    end

    shared.pending_joins[pending_join_key(full_room, bare_jid)] = {
        room_name = room_name;
        participant_id = participant_id;
        token = token;
        schedule_generation = generation;
        role = is_host and "host" or "applicant";
    };
end

local function send_presence_event(pending, event_name)
    local body = cjson.encode({
        room_name = pending.room_name;
        participant_id = pending.participant_id;
        token = pending.token;
        event = event_name;
        schedule_generation = pending.schedule_generation;
    });
    if not body then
        return false;
    end

    local response = request_with_deadline(presence_url, body);
    if not response or response.code == 0 then
        module:log("warn", "NEXIAM presence callback unavailable");
        return false;
    end
    local decoded = cjson.decode(response.body or "");
    local accepted = response.code == 200 and type(decoded) == "table" and decoded.accepted == true
        and decoded.room_name == pending.room_name and decoded.generation == pending.schedule_generation;
    if not accepted then
        module:log("warn", "NEXIAM presence callback rejected a room lifecycle transition");
    end
    return accepted;
end

local function confirm_join(event)
    local room = event.room;
    local occupant = event.occupant;
    if not room or not occupant or jitsi_util.is_healthcheck_room(room.jid)
        or usermanager.is_admin(occupant.bare_jid) then
        return;
    end
    local key = pending_join_key(room.jid, occupant.bare_jid);
    local pending = shared.pending_joins[key];
    shared.pending_joins[key] = nil;
    if not pending then
        room:set_role(true, occupant.nick, nil, "Consultation access could not be verified");
        return;
    end
    if shared.fenced_rooms[room.jid] or not send_presence_event(pending, "joined") then
        room:set_role(true, occupant.nick, nil, "Consultation access could not be confirmed");
        return;
    end
    shared.active_joins[key] = pending;
end

local function clear_join_cache(event)
    local room = event.room;
    local occupant = event.occupant;
    if not room or not occupant or jitsi_util.is_healthcheck_room(room.jid)
        or usermanager.is_admin(occupant.bare_jid) then
        return;
    end
    local key = pending_join_key(room.jid, occupant.bare_jid);
    shared.pending_joins[key] = nil;
    local active = shared.active_joins[key];
    shared.active_joins[key] = nil;
    if not active then
        return;
    end

    if not shared.terminating_rooms[room.jid] then
        send_presence_event(active, "left");
    elseif active.role == "host" then
        send_presence_event(active, "left");
    end

    if active.role == "host" then
        for _, other in room:each_occupant() do
            if room:get_affiliation(other.bare_jid) == "member" then
                room:set_affiliation(true, other.bare_jid, "none");
            end
        end
    end
end


local function enable_room_lobby(event)
    local room = event.room;
    if not room or jitsi_util.is_healthcheck_room(room.jid) then
        return;
    end
    room:set_members_only(true);
    if not room._data.lobbyroom then
        prosody.events.fire_event("create-persistent-lobby-room", {
            room = room;
            skip_display_name_check = true;
        });
    end
    if not room._data.lobbyroom or room._data.persist_lobby ~= true or not room:get_persistent() then
        module:log("error", "Native persistent lobby setup failed");
    end
end

local function keep_lobby_enabled(event)
    if event.room and not event.room:get_members_only() then
        enable_room_lobby(event);
    end
end

module:hook("muc-room-created", enable_room_lobby, 10);
module:hook("muc-config-submitted", keep_lobby_enabled, -10);
module:hook("muc-occupant-pre-join", authorize_join, 90);
module:hook("muc-occupant-joined", confirm_join);
module:hook("muc-occupant-left", clear_join_cache);
shared.admission_hook_installed = true;
shared.postjoin_hook_installed = true;
shared.lobby_hooks_installed = true;

function module.unload()
    shared.admission_hook_installed = nil;
    shared.postjoin_hook_installed = nil;
    shared.lobby_hooks_installed = nil;
end

