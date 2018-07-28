local utils = require "weserv.helpers.utils"
local ngx = ngx
local os = os
local setmetatable = setmetatable

--- Weserv module.
-- @module weserv
local weserv = {}
weserv.__index = weserv

--- Instantiate a Weserv object:
-- The client is responsible for downloading an image from the http(s)
-- origin.
-- @see weserv.client.request
-- The api is responsible for processing the downloaded image.
-- @see weserv.api.api.process
-- The server is responsible for outputting the processed image towards
-- the user/browser.
-- @see weserv.server.output
-- @param client client object
-- @param client The api
-- @param client The server
local function new(client, api, server)
    local self = {
        client = client,
        api = api,
        server = server,
    }
    return setmetatable(self, weserv)
end

--- Start the app.
-- @param args The URL query arguments.
function weserv:run(args)
    args.url = utils.clean_uri(args.url)

    local res, client_err = self.client:request(args.url)

    if not res then
        if args.errorredirect ~= nil then
            local redirect_uri = utils.clean_uri(args.errorredirect)

            local parsed_uri = utils.parse_uri(redirect_uri)
            if not parsed_uri then
                ngx.status = ngx.HTTP_NOT_FOUND
                ngx.header['Content-Type'] = 'text/plain'
                ngx.say(client_err)
            else
                ngx.redirect(redirect_uri)
            end
        else
            ngx.status = ngx.HTTP_NOT_FOUND
            ngx.header['Content-Type'] = 'text/plain'
            ngx.say(client_err)
        end
    else
        local image, api_err = self.api:process(res.tmpfile, args)

        if image ~= nil then
            -- Output the image.
            self.server.output(image, args)
        else
            ngx.status = api_err.status
            ngx.header['Content-Type'] = 'text/plain'
            ngx.say(api_err.message)
        end

        -- Remove the temporary file.
        os.remove(res.tmpfile)
    end
end

return {
    new = new,
    __object = weserv
}