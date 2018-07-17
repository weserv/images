local template = require "resty.template"
local utils = require "weserv.helpers.utils"
local redis = require "resty.redis"
local server = require "weserv.server"
local api = require "weserv.api"
local throttler = require "weserv.throttler"
local policy = require "weserv.policy"
local ngx = ngx
local os = os
local math = math
local string = string
local setmetatable = setmetatable

local weserv = {}
weserv.__index = weserv

-- Instantiate a Weserv object:
--
-- @param config The config
local function new(config)
    local self = {
        config = config,
        api = api.new(config),
    }
    return setmetatable(self, weserv)
end

-- Register a manipulator to be used.
--
-- @param manipulator The manipulator
function weserv:add_manipulator(manipulator)
    self.api:add_manipulator(manipulator)
end

-- Register some manipulators to be used.
--
-- @param manipulators The manipulators
function weserv:add_manipulators(manipulators)
    self.api:add_manipulators(manipulators)
end

function weserv.use_old_api(args)
    local random = math.random(1, 10)

    return args.api == '3' or random > 1
end

-- Start the app.
function weserv:run()
    local args, err = ngx.req.get_uri_args()

    if args.api ~= '4' and self.use_old_api(args) then
        ngx.exec("/index.php", args)
        return
    end

    local ip_address = ngx.var.remote_addr

    local redis_throttler

    -- If config throttler is set and IP isn't on the throttler whitelist
    if self.config.throttler ~= nil and self.config.throttler.whitelist[ip_address] == nil then
        local throttling_policy = policy.new(self.config.throttler.policy)

        local red = redis:new()
        red:set_timeout(self.config.throttler.redis.timeout)

        local redis_ip, redis_port = self.config.throttler.redis.host, self.config.throttler.redis.port

        local ok, err = red:connect(redis_ip, redis_port)
        if not ok then
            ngx.log(ngx.ERR, string.format("Failed to connect to redis (for %s:%d)", redis_ip, redis_port), err)
        else
            redis_throttler = throttler.new(red, throttling_policy, self.config.throttler)
        end
    end

    if redis_throttler ~= nil and redis_throttler:is_exceeded(ip_address) then
        ngx.status = ngx.HTTP_TOO_MANY_REQUESTS
        ngx.header['Content-Type'] = 'text/plain'
        ngx.say('429 Too Many Requests - There are an unusual number of requests coming from this IP address.')
    elseif err == 'truncated' then
        ngx.status = ngx.HTTP_BAD_REQUEST
        ngx.header['Content-Type'] = 'text/plain'
        ngx.say('400 Bad Request - Request arguments limit is exceeded. A maximum of 100 request arguments are parsed.')
    elseif args.url ~= nil and args.url ~= '' then
        local image, err = self.api:run(args)

        if image ~= nil then
            -- Output the image.
            server.output_image(image, args)

            -- Remove the temporary file.
            os.remove(args.tmp_file_name)
        elseif err.status == ngx.HTTP_MOVED_TEMPORARILY then
            -- Use old API for GIF images.
            ngx.exec("/index.php", args)
        elseif err.status == ngx.HTTP_NOT_FOUND and args.errorredirect ~= nil then
            local uri = utils.clean_uri(args.errorredirect)

            local parsed_uri, parse_err = utils.parse_uri(uri)
            if not parsed_uri then
                ngx.status = err.status
                ngx.header['Content-Type'] = 'text/plain'
                ngx.say(err.message)
            else
                ngx.redirect(uri)
            end
        else
            ngx.status = err.status
            ngx.header['Content-Type'] = 'text/plain'
            ngx.say(err.message)
        end

    else
        ngx.status = ngx.HTTP_OK
        ngx.header['Content-Type'] = 'text/html'
        template.render('index.html', self.config.template)
    end
end

return {
    new = new,
    __object = weserv
}