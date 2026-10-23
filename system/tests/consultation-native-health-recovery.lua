package.path = "/usr/share/lua/5.4/prosody/?.lua;" .. package.path;

local cjson = require "cjson.safe";
local timers = {};
local clock = 10000;
local transport_mode = "healthy";
local routes;
local control_token = string.rep("c", 48);
local shared = {
    admission_hook_installed = true;
    postjoin_hook_installed = true;
    lobby_hooks_installed = true;
};
local options = {
    nexiam_admission_url = "http://callback.test/admission";
    nexiam_presence_url = "http://callback.test/presence";
    nexiam_admission_secret = string.rep("a", 48);
    nexiam_control_token = control_token;
    nexiam_muc_component = "muc.test";
};
local test_module = { host = "test"; };
function test_module:get_option_string(key) return options[key]; end
function test_module:get_option_number(_, default) return default; end
function test_module:shared(_) return shared; end
function test_module:log(...) end
function test_module:provides(_, definition) routes = definition.route; end
function test_module:add_timer(delay, callback)
    local timer = { delay = delay; callback = callback; stopped = false; fired = false; };
    function timer:stop() self.stopped = true; end
    timers[#timers + 1] = timer;
    return timer;
end

local http = {};
function http.request(url, _, callback)
    local request = { callback = callback; };
    -- Prosody net.http cancellation takes the request as its first argument.
    function request.cancel(self, reason)
        if self.callback then
            self.callback(reason or "cancelled", 0, self);
            self.callback = nil;
        end
    end
    if transport_mode == "healthy" then
        if url == options.nexiam_admission_url then
            callback('{"allowed":false}', 200);
        else
            callback('{"accepted":false}', 403);
        end
        request.callback = nil;
    end
    return request;
end

local environment = setmetatable({
    module = test_module;
    os = { time = function() return clock; end; };
    prosody = { hosts = {
        test = { modules = { muc_lobby_rooms = {}; persistent_lobby = {}; }; };
        ["muc.test"] = { modules = { muc = {}; nexiam_admission = {}; }; };
    }; };
    require = function(name)
        if name == "net.http" then return http; end
        return require(name);
    end;
}, { __index = _G; });
assert(loadfile("/prosody-plugins-custom/mod_nexiam_control.lua", "t", environment))();

local function fire_next(delay)
    for _, timer in ipairs(timers) do
        if timer.delay == delay and not timer.stopped and not timer.fired then
            timer.fired = true;
            timer.callback();
            return;
        end
    end
    error("Expected timer was not scheduled: " .. delay);
end
local function health()
    return routes["POST /health"]({ request = {
        headers = { authorization = "Bearer " .. control_token; };
        body = cjson.encode({ nonce = "timeout-recovery-nonce"; });
    }; });
end

fire_next(0.1);
assert(health().status_code == 200, "Initial callback attestation must be healthy");

transport_mode = "timeout";
fire_next(5);
clock = clock + 3;
fire_next(3);
fire_next(3);
local failed = health();
assert(failed.status_code == 503, "A completed failed probe must invalidate previous readiness");
assert(cjson.decode(failed.body).error == "callback_attestation_unavailable");

transport_mode = "healthy";
clock = clock + 2;
fire_next(5);
local recovered = health();
assert(recovered.status_code == 200, "A later healthy probe must recover without restarting Prosody");
local evidence = cjson.decode(recovered.body);
assert(evidence.nonce == "timeout-recovery-nonce");
assert(evidence.callbacks_age_seconds == 0);

clock = clock + 11;
assert(health().status_code == 503, "Stale successful evidence must remain fail-closed");
print("PASS: healthy -> callback timeout -> unavailable -> recovered; stale evidence denied");
