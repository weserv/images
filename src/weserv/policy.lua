local http = require "resty.http"
local cjson = require "cjson"
local os = os
local ngx = ngx
local string = string
local setmetatable = setmetatable

--- Throttler policy module.
-- @module policy
local policy = {}
policy.__index = policy

--- Instantiate a throttler policy object.
-- @param config Throttler policy config.
local function new(config)
    local self = {
        config = config,
    }
    return setmetatable(self, policy)
end

--- How long should the user be banned?
-- @return ban time in minutes.
function policy:get_ban_time()
    return self.config.ban_time
end

--- Is the CloudFlare provider enabled?
-- @return true if enabled, false otherwise.
function policy:is_cloudflare_enabled()
    return self.config.cloudflare.enabled
end

--- Ban or challenge (such as a CAPTCHA) at CloudFlare.
-- @param ip_address The IP address.
-- @return true if the ban was successful, false otherwise.
function policy:ban_at_cloudflare(ip_address)
    local body = cjson.encode({
        mode = self.config.cloudflare.mode,
        configuration = {
            target = "ip",
            value = ip_address,
        },
        notes = "Banned until " .. os.date("%Y-%m-%dT%H:%M:%SZ", os.time() + (self:get_ban_time() * 60)),
    })

    local httpc = http.new()
    local uri_template = "https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules"
    local uri = string.format(uri_template, self.config.cloudflare.zone_id)
    local res, request_err = httpc:request_uri(uri, {
        method = "POST",
        body = body,
        headers = {
            ["X-Auth-Email"] = self.config.cloudflare.email,
            ["X-Auth-Key"] = self.config.cloudflare.auth_key,
            ["Content-Type"] = "application/json",
        },
        ssl_verify = false,
    })

    if not res then
        ngx.log(ngx.ERR, string.format("Failed to ban (for %s) at CloudFlare: ", ip_address), request_err)
        return false
    end

    if res.status ~= ngx.HTTP_OK then
        ngx.log(ngx.ERR, string.format("Failed to ban (for %s) at CloudFlare: ", ip_address), res.body)
        return false
    end

    local response = cjson.decode(res.body)
    local success = response.result ~= cjson.null and response.result.id ~= cjson.null
    if not success then
        ngx.log(ngx.ERR, string.format("Failed to ban (for %s) at CloudFlare: ", ip_address), res.body)
        return false
    end

    ngx.log(ngx.NOTICE, string.format("Successfully banned %s (identifier %s) at CloudFlare",
        ip_address, response.result.id))

    return true
end

--- Unban a identifier from CloudFlare.
-- @param identifier The identifier.
-- @return true if the unban was successful, false otherwise.
function policy:unban_at_cloudflare(identifier)
    local body = cjson.encode({
        cascade = "none",
    })

    local httpc = http.new()
    local uri_template = "https://api.cloudflare.com/client/v4/zones/%s/firewall/access_rules/rules/%s"
    local uri = string.format(uri_template, self.config.cloudflare.zone_id, identifier)
    local res, request_err = httpc:request_uri(uri, {
        method = "DELETE",
        body = body,
        headers = {
            ["X-Auth-Email"] = self.config.cloudflare.email,
            ["X-Auth-Key"] = self.config.cloudflare.auth_key,
            ["Content-Type"] = "application/json",
        },
        ssl_verify = false,
    })

    if not res then
        ngx.log(ngx.ERR, string.format("Failed to unban (for identifier %s) at CloudFlare: ", identifier), request_err)
        return false
    end

    if res.status ~= ngx.HTTP_OK then
        ngx.log(ngx.ERR, string.format("Failed to unban (for identifier %s) at CloudFlare: ", identifier), res.body)
        return false
    end

    local response = cjson.decode(res.body)
    local success = response.result ~= cjson.null and response.result.id ~= cjson.null
    if not success then
        ngx.log(ngx.ERR, string.format("Failed to unban (for identifier %s) at CloudFlare: ", identifier), res.body)
        return false
    end

    ngx.log(ngx.NOTICE, string.format("Successfully unbanned identifier %s at CloudFlare", identifier))

    return true
end

return {
    new = new,
    __object = policy
}