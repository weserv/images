local match = require "luassert.match"
local cjson = require "cjson"

describe("throttling policy", function()
    local default_config = {
        ban_time = 60, -- If exceed, ban for 60 minutes
        cloudflare = {
            enabled = true, -- Is CloudFlare enabled?
            email = 'user@example.com',
            auth_key = '',
            zone_id = '7c5dae5552338874e5053f2534d2767a',
            mode = 'block' -- The action to apply if the IP get's banned
        }
    }

    local old_ngx = _G.ngx
    local old_http = package.loaded["resty.http"]
    local stubbed_http
    local policy

    local request_error
    local response = {
        status = 200,
        body = ''
    }

    setup(function()
        local stubbed_ngx = {
            -- luacheck: globals ngx._logs
            _logs = {},
        }
        stubbed_ngx.log = function(...)
            stubbed_ngx._logs[#stubbed_ngx._logs + 1] = table.concat({ ... }, " ")
        end

        -- Busted requires explicit _G to access the global environment
        _G.ngx = setmetatable(stubbed_ngx, { __index = old_ngx })

        stubbed_http = {
            request_uri = function(_, _, _)
                if request_error ~= nil then
                    return nil, request_error
                end

                return response, nil
            end,
        }
        spy.on(stubbed_http, "request_uri")

        package.loaded["resty.http"] = {
            new = function()
                return stubbed_http
            end
        }

        local weserv_policy = require "weserv.policy"
        policy = weserv_policy.new(default_config)
    end)

    teardown(function()
        _G.ngx = old_ngx
        package.loaded["resty.http"] = old_http
    end)

    after_each(function()
        -- Clear logs, error, call history and response after each test
        _G.ngx._logs = {}
        request_error = nil
        stubbed_http.request_uri:clear()
        response = {
            status = 200,
            body = ''
        }
    end)

    it("test get ban time", function()
        assert.equal(default_config.ban_time, policy:get_ban_time())
    end)

    it("test is cloudflare enabled", function()
        assert.equal(default_config.cloudflare.enabled, policy:is_cloudflare_enabled())
    end)

    it("test ban", function()
        response.body = cjson.encode({
            result = {
                id = "92f17202ed8bd63d69a66b86a49a8f6b",
                paused = false,
                modified_on = "2018-07-27T00:00:00.000000000Z",
                allowed_modes = {
                    "whitelist",
                    "block",
                    "challenge",
                    "js_challenge"
                },
                mode = "block",
                notes = "Banned until " .. os.date("%Y-%m-%dT%H:%M:%SZ", os.time() + (default_config.ban_time * 60)),
                configuration = {
                    target = "ip",
                    value = "127.0.0.1"
                },
                scope = {
                    id = '7c5dae5552338874e5053f2534d2767a',
                    name = 'weserv.nl',
                    type = 'zone',
                },
                created_on = "2018-07-27T00:00:00.000000000Z"
            },
            success = true,
            errors = cjson.null,
            messages = cjson.null,
        })

        local ip_address = '127.0.0.1'
        local uri_template = "https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules"
        local uri = string.format(uri_template, default_config.cloudflare.zone_id)

        assert.True(policy:ban_at_cloudflare(ip_address))
        assert.spy(stubbed_http.request_uri).was_called_with(match._, uri, match.contains({
            method = 'POST'
        }))

        -- Log successful ban notices
        assert.equal(1, #ngx._logs)
    end)

    it("test unban", function()
        response.body = cjson.encode({
            result = {
                id = "92f17202ed8bd63d69a66b86a49a8f6b",
            },
            success = true,
            errors = {},
            messages = {}
        })

        local identifier = '92f17202ed8bd63d69a66b86a49a8f6b'
        local uri_template = "https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules/%s"
        local uri = string.format(uri_template, default_config.cloudflare.zone_id, identifier)

        assert.True(policy:unban_at_cloudflare(identifier))
        assert.spy(stubbed_http.request_uri).was_called_with(match._, uri, match.contains({
            method = 'DELETE'
        }))

        -- Log successful unban notices
        assert.equal(1, #ngx._logs)
    end)

    it("test already banned", function()
        response.body = cjson.encode({
            result = cjson.null,
            success = false,
            errors = {
                {
                    code = 10009,
                    message = "firewallaccessrules.api.duplicate_of_existing"
                }
            },
            messages = {}
        })

        local ip_address = '127.0.0.1'

        assert.False(policy:ban_at_cloudflare(ip_address))

        -- Log unsuccessful ban errors
        assert.equal(1, #ngx._logs)
    end)

    it("test unknown identifier unban", function()
        response.body = cjson.encode({
            result = cjson.null,
            success = true,
            errors = cjson.null,
            messages = cjson.null,
        })

        local identifier = 'f80165d0226f419987c9fc7651e5e8b4'

        assert.False(policy:unban_at_cloudflare(identifier))

        -- Log unsuccessful unban errors
        assert.equal(1, #ngx._logs)
    end)

    it("test ban status 400", function()
        response.status = 400

        local ip_address_invalid = '127.invalid'

        assert.False(policy:ban_at_cloudflare(ip_address_invalid))

        -- Log bad response errors
        assert.equal(1, #ngx._logs)
    end)

    it("test unban status 400", function()
        response.status = 400

        local identifier_invalid = 'foobar'

        assert.False(policy:unban_at_cloudflare(identifier_invalid))

        -- Log bad response errors
        assert.equal(1, #ngx._logs)
    end)

    it("test ban request error", function()
        request_error = 'timeout'

        local ip_address = '127.0.0.1'

        assert.False(policy:ban_at_cloudflare(ip_address))

        -- Log request errors
        assert.equal(1, #ngx._logs)
    end)

    it("test unban request error", function()
        request_error = 'timeout'

        local identifier = '92f17202ed8bd63d69a66b86a49a8f6b'

        assert.False(policy:unban_at_cloudflare(identifier))

        -- Log request errors
        assert.equal(1, #ngx._logs)
    end)
end)