local http = require "net.http";
local promise = require "util.promise";
local cjson = require "cjson.safe";

local admission_url = module:get_option_string("nexiam_admission_url");
local presence_url = module:get_option_string("nexiam_presence_url");
local admission_secret = module:get_option_string("nexiam_admission_secret");
local control_token = module:get_option_string("nexiam_control_token");
local callback_timeout = module:get_option_number("nexiam_admission_timeout", 3);
local muc_component = module:get_option_string("nexiam_muc_component");
local shared = module:shared("/nexiam_admission");
shared.fenced_rooms = shared.fenced_rooms or {};
shared.pending_joins = shared.pending_joins or {};
shared.active_joins = shared.active_joins or {};
shared.terminating_rooms = shared.terminating_rooms or {};

if not admission_url or admission_url == "" or not presence_url or presence_url == ""
    or not admission_secret or #admission_secret < 32 or not control_token or #control_token < 32
    or not muc_component then
    module:log("error", "Required NEXIAM control configuration is missing or too short");
    return;
end

local function json_response(status, payload)
    return {
        status_code = status;
        headers = { content_type = "application/json", ["cache-control"] = "no-store" };
        body = cjson.encode(payload) or "{}";
    };
end

local function room_key(room_name)
    if type(room_name) ~= "string" or #room_name < 1 or #room_name > 200
        or not room_name:match("^[a-z0-9][a-z0-9_-]*$") then
        return nil;
    end
    return room_name .. "@" .. muc_component;
end

local function is_control_request(event)
    local headers = event.request.headers or {};
    local authorization = headers.authorization or headers.Authorization;
    if type(authorization) ~= "string" then
        return false;
    end
    local supplied = authorization:match("^Bearer ([^%s]+)$");
    return supplied ~= nil and #supplied == #control_token and supplied == control_token;
end

local function decode_body(event)
    if type(event.request.body) ~= "string" or #event.request.body > 8192 then
        return nil;
    end
    return cjson.decode(event.request.body);
end

local function request_with_deadline(url, body)
    return promise.new(function(resolve)
        local settled = false;
        local request;
        local timeout = module:add_timer(callback_timeout, function()
            if settled then
                return;
            end
            settled = true;
            if request and request.cancel then
                request:cancel();
            end
            resolve({ timeout = true; });
        end);

        request = http.request(url, {
            method = "POST";
            body = body;
            headers = {
                ["Content-Type"] = "application/json";
                ["Accept"] = "application/json";
                ["X-Jitsi-Admission-Secret"] = admission_secret;
            };
        }, function(response_body, code, response)
            if settled then
                return;
            end
            settled = true;
            timeout:stop();
            resolve({ body = response_body; code = code; response = response; });
        end);
    end);
end


local function callback_probe(url, expected_status)
    local body = cjson.encode({
        room_name = "nexiam-health-probe";
        participant_id = "nexiam-health-probe";
        token = "invalid-health-probe";
        event = "joined";
        schedule_generation = 1;
    });
    return request_with_deadline(url, body):next(function(result)
        if result.timeout or result.code == 0 then
            module:log("warn", "NEXIAM callback readiness probe failed");
            return false;
        end
        if expected_status == 200 then
            local decoded = cjson.decode(result.body or "");
            local allowed = result.code == 200 and type(decoded) == "table" and decoded.allowed == false;
            if not allowed then
                module:log("warn", "NEXIAM admission readiness probe failed");
            end
            return allowed;
        end
        if result.code ~= expected_status then
            module:log("warn", "NEXIAM presence readiness probe failed");
            return false;
        end
        return true;
    end);
end

local function lobby_is_available()
    local host = prosody.hosts[module.host];
    return host ~= nil and host.modules ~= nil and host.modules.muc_lobby_rooms ~= nil
        and host.modules.persistent_lobby ~= nil;
end
local callback_attestation = {
    in_flight = false;
    latest_probe_ok = false;
    callbacks_verified_at = nil;
};

local function dependencies_available()
    local muc_host = prosody.hosts[muc_component];
    local admission_module = muc_host and muc_host.modules and muc_host.modules.nexiam_admission;
    return lobby_is_available() and muc_host ~= nil and muc_host.modules ~= nil
        and muc_host.modules.muc ~= nil and admission_module ~= nil
        and shared.admission_hook_installed == true and shared.postjoin_hook_installed == true
        and shared.lobby_hooks_installed == true;
end

local function run_callback_probe()
    if callback_attestation.in_flight then
        return;
    end
    callback_attestation.in_flight = true;

    if not dependencies_available() then
        callback_attestation.in_flight = false;
        callback_attestation.latest_probe_ok = false;
        callback_attestation.callbacks_verified_at = nil;
        return;
    end

    promise.all({
        callback_probe(admission_url, 200);
        callback_probe(presence_url, 403);
    }):next(function(results)
        callback_attestation.in_flight = false;
        if dependencies_available() and results[1] == true and results[2] == true then
            callback_attestation.latest_probe_ok = true;
            callback_attestation.callbacks_verified_at = os.time();
        else
            callback_attestation.latest_probe_ok = false;
            callback_attestation.callbacks_verified_at = nil;
        end
    end, function(err)
        callback_attestation.in_flight = false;
        callback_attestation.latest_probe_ok = false;
        callback_attestation.callbacks_verified_at = nil;
        module:log("warn", "NEXIAM background callback probe failed");
    end);
end

local function schedule_callback_probe()
    module:add_timer(5, function()
        run_callback_probe();
        schedule_callback_probe();
    end);
end

module:add_timer(0.1, function()
    run_callback_probe();
    schedule_callback_probe();
end);

local function health(event)
    if not is_control_request(event) then
        return json_response(401, { error = "unauthorized" });
    end
    local body = decode_body(event);
    if type(body) ~= "table" or type(body.nonce) ~= "string" or #body.nonce < 16 or #body.nonce > 128 then
        return json_response(400, { error = "invalid_nonce" });
    end
    if not dependencies_available() then
        return json_response(503, { error = "admission_hook_unavailable" });
    end
    local verified_at = callback_attestation.callbacks_verified_at;
    local age = verified_at and os.time() - verified_at or nil;
    if callback_attestation.latest_probe_ok ~= true or not age or age < 0 or age > 10 then
        return json_response(503, { error = "callback_attestation_unavailable" });
    end
    return json_response(200, {
        nonce = body.nonce;
        protocol_version = 1;
        admission_hook_installed = true;
        admission_callback_reachable = true;
        admission_callback_status = 200;
        presence_callback_reachable = true;
        mandatory_lobby_installed = true;
        callbacks_verified_at = verified_at;
        callbacks_age_seconds = age;
    });
end

local function terminate(event)
    if not is_control_request(event) then
        return json_response(401, { error = "unauthorized" });
    end
    local body = decode_body(event);
    if type(body) ~= "table" or type(body.request_id) ~= "string" or #body.request_id < 16 or #body.request_id > 128
        or type(body.generation) ~= "number" or body.generation < 1 or body.generation % 1 ~= 0 then
        return json_response(400, { error = "invalid_termination_request" });
    end

    local room_jid = room_key(body.room_name);
    if not room_jid then
        return json_response(400, { error = "invalid_room_name" });
    end
    local previous_generation = shared.fenced_rooms[room_jid];
    if previous_generation and body.generation < previous_generation then
        return json_response(409, { error = "stale_generation" });
    end

    -- Install the fence before touching the room, so a concurrent pre-join cannot race termination.
    shared.fenced_rooms[room_jid] = body.generation;
    local prefix = room_jid .. "\0";
    for key in pairs(shared.pending_joins) do
        if key:sub(1, #prefix) == prefix then
            shared.pending_joins[key] = nil;
        end
    end

    local muc = prosody.hosts[muc_component] and prosody.hosts[muc_component].modules.muc;
    if not muc then
        return json_response(503, { error = "muc_service_unavailable" });
    end
    local room = muc.get_room_from_jid(room_jid);
    if room then
        shared.terminating_rooms[room_jid] = true;
        local destroyed = room:destroy(nil, "Consultation closed");
        shared.terminating_rooms[room_jid] = nil;
        if not destroyed or muc.get_room_from_jid(room_jid) ~= nil then
            return json_response(503, { error = "room_termination_unconfirmed" });
        end
    end

    return json_response(200, {
        room_name = body.room_name;
        request_id = body.request_id;
        generation = body.generation;
        terminated = true;
    });
end

module:provides("http", {
    name = "nexiam_admission";
    route = {
        ["POST /health"] = health;
        ["POST /terminate"] = terminate;
    };
});


