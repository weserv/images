local ngx = ngx
local string = string
local setmetatable = setmetatable

local throttler = {}
throttler.__index = throttler

-- Instantiate a throttler object.
--
-- @param redis Redis instance.
-- @param policy Throttling policy.
-- @param config Throttler config.
local function new(redis, policy, config)
    local self = {
        redis = redis,
        policy = policy,
        config = config,
    }
    return setmetatable(self, throttler)
end

-- Determine if any rate limits have been exceeded.
--
-- @param ip_address The IP address.
-- @return Boolean indicating if the rate limit have been exceeded.
function throttler:is_exceeded(ip_address)
    if self.redis:exists(string.format("%s_%s:lockout", self.config.prefix, ip_address)) ~= 0 then
        return true
    end

    if self:increment(ip_address, self.config.minutes) > self.config.allowed_requests then
        local ttl = self.policy:get_ban_time() * 60
        local expires = ngx.time() + ttl

        -- Is CloudFlare enabled?
        if self.policy:is_cloudflare_enabled() then
            self.policy:ban_at_cloudflare(ip_address)
        end

        local ok, err = self.redis:set(string.format("%s_%s:lockout", self.config.prefix, ip_address), expires, 'ex', ttl)
        if not ok then
            ngx.log(ngx.ERR, string.format("Failed to set lockout key (for %s)", ip_address), err)
        end

        self:reset_attempts(ip_address)
        return true
    end

    return false
end

-- Increment the counter for a given ip address for a given decay time.
--
-- @param ip_address The IP address.
-- @param decay_minutes Decay time in minutes.
-- @return Value after increment.
function throttler:increment(ip_address, decay_minutes)
    local value, err = self.redis:incr(string.format("%s_%s", self.config.prefix, ip_address))
    if not value then
        ngx.log(ngx.ERR, string.format("Failed to increment (for %s)", ip_address), err)
        return -1
    end

    -- Check if this increment is new, and if so set expires time
    if value == 1 then
        local res, err = self.redis:expire(string.format("%s_%s", self.config.prefix, ip_address), decay_minutes * 60)
        if not res then
            ngx.log(ngx.ERR, string.format("Failed to set expiry (for %s) to %d", ip_address, decay_minutes * 60), err)
        end
    end
    return value
end

-- Get the number of attempts for the given ip address.
--
-- @param ip_address The IP address.
-- @return Number of attempts for the given ip address.
function throttler:attempts(ip_address)
    local attempts, err = self.redis:get(string.format("%s_%s", self.config.prefix, ip_address))
    if not attempts then
        ngx.log(ngx.ERR, string.format("Failed to get number of attempts (for %s)", ip_address), err)
        return -1
    end

    return attempts
end

-- Reset the number of attempts for the given ip address.
--
-- @param ip_address The IP address.
-- @return true on success, false otherwise.
function throttler:reset_attempts(ip_address)
    local res, err = self.redis:del(string.format("%s_%s", self.config.prefix, ip_address))
    if not res then
        ngx.log(ngx.ERR, string.format("Failed to reset number of attempts (for %s)", ip_address), err)
        return false
    end

    return true
end

-- Get the number of retries left for the given ip address.
--
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

-- Clear the hits and lockout for the given ip address.
--
-- @param ip_address The IP address.
-- @return Number of retries left for the given ip address.
function throttler:clear(ip_address)
    self:reset_attempts(ip_address)
    local res, err = self.redis:del(string.format("%s_%s:lockout", self.config.prefix, ip_address))
    if not res then
        ngx.log(ngx.ERR, string.format("Failed to delete lockout key (for %s)", ip_address), err)
    end
end

-- Get the number of seconds until the ip address is accessible again.
--
-- @param ip_address The IP address.
-- @return Number of seconds until the ip address is accessible again.
function throttler:available_in(ip_address)
    local expires, err = self.redis:get(string.format("%s_%s:lockout", self.config.prefix, ip_address))
    if not expires then
        ngx.log(ngx.ERR, string.format("Failed to get expires time (for %s)", ip_address), err)
        return -1
    end
    return expires - ngx.time()
end

return {
    new = new,
    __object = throttler
}