local setmetatable = setmetatable
local ngx_log = ngx.log
local ngx_ERR = ngx.ERR
local ngx_time = ngx.time
local str_format = string.format

--- Throttler module.
-- @module throttler
local throttler = {}
local mt = { __index = throttler }

--- Instantiate a throttler object.
-- @param redis Redis instance.
-- @param policy Throttling policy.
-- @param config Throttler config.
function throttler.new(redis, policy, config)
    return setmetatable({
        redis = redis,
        policy = policy,
        config = config,
    }, mt)
end

--- Determine if any rate limits have been exceeded.
-- @param ip_address The IP address.
-- @return Boolean indicating if the rate limit have been exceeded.
function throttler:is_exceeded(ip_address)
    if self.redis:exists(str_format("%s_%s:lockout", self.config.prefix, ip_address)) ~= 0 then
        return true
    end

    if self:increment(ip_address, self.config.minutes) > self.config.allowed_requests then
        local ttl = self.policy:get_ban_time() * 60
        local expires = ngx_time() + ttl

        -- Is CloudFlare enabled?
        if self.policy:is_cloudflare_enabled() then
            self.policy:ban_at_cloudflare(ip_address)
        end

        local ok, set_err = self.redis:set(str_format("%s_%s:lockout", self.config.prefix, ip_address),
            expires, "ex", ttl)
        if not ok then
            ngx_log(ngx_ERR, str_format("Failed to set lockout key (for %s): ", ip_address), set_err)
        end

        self:reset_attempts(ip_address)
        return true
    end

    return false
end

--- Increment the counter for a given ip address for a given decay time.
-- @param ip_address The IP address.
-- @param decay_minutes Decay time in minutes.
-- @return Value after increment.
function throttler:increment(ip_address, decay_minutes)
    local value, incr_err = self.redis:incr(str_format("%s_%s", self.config.prefix, ip_address))
    if not value then
        ngx_log(ngx_ERR, str_format("Failed to increment (for %s): ", ip_address), incr_err)
        return -1
    end

    -- Check if this increment is new, and if so set expires time
    if value == 1 then
        local ttl = decay_minutes * 60
        local res, expire_err = self.redis:expire(str_format("%s_%s", self.config.prefix, ip_address), ttl)
        if not res then
            ngx_log(ngx_ERR, str_format("Failed to set expiry (for %s) to %d: ", ip_address, ttl), expire_err)
        end
    end
    return value
end

--- Get the number of attempts for the given ip address.
-- @param ip_address The IP address.
-- @return Number of attempts for the given ip address.
function throttler:attempts(ip_address)
    local attempts, get_err = self.redis:get(str_format("%s_%s", self.config.prefix, ip_address))
    if not attempts then
        ngx_log(ngx_ERR, str_format("Failed to get number of attempts (for %s): ", ip_address), get_err)
        return -1
    end

    return attempts
end

--- Reset the number of attempts for the given ip address.
-- @param ip_address The IP address.
-- @return true on success, false otherwise.
function throttler:reset_attempts(ip_address)
    local res, del_err = self.redis:del(str_format("%s_%s", self.config.prefix, ip_address))
    if not res then
        ngx_log(ngx_ERR, str_format("Failed to reset number of attempts (for %s): ", ip_address), del_err)
        return false
    end

    return true
end

--- Get the number of retries left for the given ip address.
-- @param ip_address The IP address.
-- @param max_attempts How many attempts maximum?
-- @return Number of retries left for the given ip address.
function throttler:retries_left(ip_address, max_attempts)
    local attempts = self:attempts(ip_address)
    if attempts <= 0 then
        return max_attempts
    end
    return max_attempts - attempts + 1
end

--- Clear the hits and lockout for the given ip address.
-- @param ip_address The IP address.
-- @return Number of retries left for the given ip address.
-- @return true on success, false otherwise.
function throttler:clear(ip_address)
    local success = self:reset_attempts(ip_address)
    local res, del_err = self.redis:del(str_format("%s_%s:lockout", self.config.prefix, ip_address))
    if not res then
        ngx_log(ngx_ERR, str_format("Failed to delete lockout key (for %s): ", ip_address), del_err)
        return false
    end
    return success
end

--- Get the number of seconds until the ip address is accessible again.
-- @param ip_address The IP address.
-- @return Number of seconds until the ip address is accessible again.
function throttler:available_in(ip_address)
    local expires, get_err = self.redis:get(str_format("%s_%s:lockout", self.config.prefix, ip_address))
    if not expires then
        ngx_log(ngx_ERR, str_format("Failed to get expires time (for %s): ", ip_address), get_err)
        return -1
    end
    return expires - ngx_time()
end

return throttler