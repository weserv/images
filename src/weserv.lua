local utils = require "weserv.helpers.utils"
local response = require "weserv.helpers.response"
local unpack = unpack
local setmetatable = setmetatable
local ngx = ngx
local ngx_redirect = ngx.redirect
local os_remove = os.remove
local HTTP_REQUEST_TIMEOUT = ngx.HTTP_REQUEST_TIMEOUT

--- Weserv module.
-- @module weserv
local weserv = {}
local mt = { __index = weserv }

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
function weserv.new(client, api, server)
    return setmetatable({
        client = client,
        api = api,
        server = server,
    }, mt)
end

--- Return an error or redirect
-- @param args The URL query arguments.
-- @param error The error table.
local function error_or_redirect(args, error)
    local parsed_redirect_uri = args.errorredirect ~= nil and utils.parse_uri(args.errorredirect) or false

    if parsed_redirect_uri then
        local scheme, host, _, path, query = unpack(parsed_redirect_uri)
        if query and query ~= "" then
            path = path .. "?" .. query
        end

        return ngx_redirect(scheme .. "://" .. host .. path)
    else
        if error.status == HTTP_REQUEST_TIMEOUT then
            -- Don't send 408, otherwise the client may repeat that request.
            return response.send_HTTP_NOT_FOUND("The requested URL returned error: Operation timed out.")
        else
            return response.send(error.status, error.message)
        end
    end
end

--- Start the app.
-- @param args The URL query arguments.
function weserv:run(args)
    local res, error = self.client:request(args.url)
    local image

    if res then
        image, error = self.api:process(res.tmpfile, args)
        if image ~= nil then
            -- Output the image.
            return self.server.output(image, args)
        end

        -- Remove the temporary file.
        os_remove(res.tmpfile)
    end

    return error_or_redirect(args, error)
end

return weserv