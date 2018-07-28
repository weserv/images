local match = require "luassert.match"

describe("throttler", function()
    local default_config = {
        allowed_requests = 700, -- 700 allowed requests
        minutes = 3, --  In 3 minutes
        prefix = 'c', -- Cache key prefix
        policy = {
            ban_time = 60, -- If exceed, ban for 60 minutes
            cloudflare = {
                enabled = true -- Is CloudFlare enabled?
            }
        }
    }

    local old_ngx = _G.ngx
    local stubbed_redis, stubbed_policy
    local throttler

    local errors = {}

    local database = {}

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

        stubbed_redis = {
            exists = function(_, key)
                if database[key] ~= nil then
                    return 1
                else
                    return 0
                end
            end,
            incr = function(_, key)
                if errors.incr ~= nil then
                    return nil, errors.incr
                end

                if database[key] ~= nil then
                    local value = database[key][1] + 1
                    database[key][1] = value

                    return value
                else
                    local value = 1
                    database[key] = { value, 0 }

                    return value
                end
            end,
            expire = function(_, key, ttl)
                if errors.expire ~= nil then
                    return nil, errors.expire
                end

                database[key][2] = ttl

                return 1
            end,
            set = function(_, key, value, ...)
                if errors.set ~= nil then
                    return nil, errors.set
                end

                local options = { ... }

                local options_tbl = {}
                for k = 1, #options, 2 do
                    options_tbl[options[k]] = options[k + 1]
                end

                database[key] = { value, options_tbl['ex'] }

                return 1
            end,
            get = function(_, key)
                if errors.get ~= nil then
                    return nil, errors.get
                end

                return database[key][1]
            end,
            del = function(_, key)
                if errors.del ~= nil then
                    return nil, errors.del
                end

                database[key] = nil

                return 1
            end
        }
        spy.on(stubbed_redis, "exists")
        spy.on(stubbed_redis, "incr")
        spy.on(stubbed_redis, "expire")
        spy.on(stubbed_redis, "set")
        spy.on(stubbed_redis, "get")
        spy.on(stubbed_redis, "del")

        stubbed_policy = {
            get_ban_time = function(_)
                return default_config.policy.ban_time
            end,
            is_cloudflare_enabled = function(_)
                return default_config.policy.cloudflare.enabled
            end,
            ban_at_cloudflare = function(_, _)
                return true
            end,
        }
        spy.on(stubbed_policy, "get_ban_time")
        spy.on(stubbed_policy, "is_cloudflare_enabled")
        spy.on(stubbed_policy, "ban_at_cloudflare")

        local weserv_throttler = require "weserv.throttler"
        throttler = weserv_throttler.new(stubbed_redis, stubbed_policy, default_config)
    end)

    teardown(function()
        _G.ngx = old_ngx
    end)

    after_each(function()
        -- Clear logs, errors, call history and database after each test
        _G.ngx._logs = {}
        errors = {}
        stubbed_redis.exists:clear()
        stubbed_redis.expire:clear()
        stubbed_redis.incr:clear()
        stubbed_redis.set:clear()
        stubbed_redis.get:clear()
        stubbed_redis.del:clear()
        database = {}
    end)

    it("test is lockout", function()
        local ip_address = '127.0.0.1'
        local lockout_key = string.format("%s_%s:lockout", default_config.prefix, ip_address)
        local lockout_ttl = default_config.policy.ban_time * 60
        local lockout_expires = os.time() + lockout_ttl
        database[lockout_key] = { lockout_expires, lockout_ttl }

        assert.True(throttler:is_exceeded(ip_address))
        assert.spy(stubbed_redis.exists).was_called_with(match._, lockout_key)
    end)

    it("test is exceeded", function()
        local ip_address = '127.0.0.1'
        local lockout_key = string.format("%s_%s:lockout", default_config.prefix, ip_address)
        local lockout_ttl = default_config.policy.ban_time * 60
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { default_config.allowed_requests, incr_ttl }

        assert.True(throttler:is_exceeded(ip_address))
        assert.spy(stubbed_redis.exists).was_called_with(match._, lockout_key)
        assert.spy(stubbed_redis.incr).was_called_with(match._, incr_key)
        assert.spy(stubbed_policy.ban_at_cloudflare).was_called_with(match._, ip_address)
        assert.spy(stubbed_redis.set).was_called_with(match._, lockout_key, match._, 'ex', lockout_ttl)
        assert.spy(stubbed_redis.del).was_called_with(match._, incr_key)
    end)

    it("test is not exceeded", function()
        local ip_address = '127.0.0.1'
        local lockout_key = string.format("%s_%s:lockout", default_config.prefix, ip_address)
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60

        assert.False(throttler:is_exceeded(ip_address))
        assert.spy(stubbed_redis.exists).was_called_with(match._, lockout_key)
        assert.spy(stubbed_redis.incr).was_called_with(match._, incr_key)
        assert.spy(stubbed_redis.expire).was_called_with(match._, incr_key, incr_ttl)
    end)

    it("test increment", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60

        assert.equal(1, throttler:increment(ip_address, default_config.minutes))
        assert.spy(stubbed_redis.incr).was_called_with(match._, incr_key)
        assert.spy(stubbed_redis.expire).was_called_with(match._, incr_key, incr_ttl)
    end)

    it("test attempts", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { 1, incr_ttl }

        assert.equal(1, throttler:attempts(ip_address))
        assert.spy(stubbed_redis.get).was_called_with(match._, incr_key)
    end)

    it("test reset attempts", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { default_config.allowed_requests + 1, incr_ttl }

        assert.True(throttler:reset_attempts(ip_address))
        assert.spy(stubbed_redis.del).was_called_with(match._, incr_key)
    end)

    it("test zero attempts all retries left", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { 0, incr_ttl }

        local max_attempts = default_config.allowed_requests
        assert.equal(max_attempts, throttler:retries_left(ip_address, max_attempts))
        assert.spy(stubbed_redis.get).was_called_with(match._, incr_key)
    end)

    it("test retries left", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { 2, incr_ttl }

        local max_attempts = default_config.allowed_requests
        assert.equal(max_attempts - 1, throttler:retries_left(ip_address, max_attempts))
        assert.spy(stubbed_redis.get).was_called_with(match._, incr_key)
    end)

    it("test clear", function()
        local ip_address = '127.0.0.1'
        local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
        local incr_ttl = default_config.minutes * 60
        database[incr_key] = { default_config.allowed_requests + 1, incr_ttl }

        local lockout_key = string.format("%s_%s:lockout", default_config.prefix, ip_address)
        local lockout_ttl = default_config.policy.ban_time * 60
        local lockout_expires = os.time() + lockout_ttl
        database[lockout_key] = { lockout_expires, lockout_ttl }

        assert.True(throttler:clear(ip_address))
        assert.spy(stubbed_redis.del).was.called(2)
        assert.spy(stubbed_redis.del).was_called_with(match._, incr_key)
        assert.spy(stubbed_redis.del).was_called_with(match._, lockout_key)
    end)

    it("test available in", function()
        local ip_address = '127.0.0.1'
        local lockout_key = string.format("%s_%s:lockout", default_config.prefix, ip_address)
        local lockout_ttl = default_config.policy.ban_time * 60
        local lockout_expires = os.time() + lockout_ttl
        database[lockout_key] = { lockout_expires, lockout_ttl }

        assert.True(throttler:available_in(ip_address) >= lockout_ttl)
        assert.spy(stubbed_redis.get).was_called_with(match._, lockout_key)
    end)

    describe("test redis error", function()
        it("set", function()
            errors.set = 'Something went wrong'

            local ip_address = '127.0.0.1'
            local incr_key = string.format("%s_%s", default_config.prefix, ip_address)
            local incr_ttl = default_config.minutes * 60
            database[incr_key] = { default_config.allowed_requests, incr_ttl }

            assert.True(throttler:is_exceeded(ip_address))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)

        it("incr", function()
            errors.incr = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.equal(-1, throttler:increment(ip_address, default_config.minutes))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)

        it("expire", function()
            errors.expire = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.equal(1, throttler:increment(ip_address, default_config.minutes))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)

        it("get attempts", function()
            errors.get = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.equal(-1, throttler:attempts(ip_address))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)

        it("del attempts", function()
            errors.del = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.False(throttler:reset_attempts(ip_address))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)

        it("del clear", function()
            errors.del = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.False(throttler:clear(ip_address))

            -- Log redis errors
            assert.equal(2, #ngx._logs)
        end)

        it("get available in", function()
            errors.get = 'Something went wrong'

            local ip_address = '127.0.0.1'

            assert.equal(-1, throttler:available_in(ip_address))

            -- Log redis errors
            assert.equal(1, #ngx._logs)
        end)
    end)
end)