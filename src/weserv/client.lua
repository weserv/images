local http = require "resty.http"
local utils = require "weserv.helpers.utils"
local ngx = ngx
local os = os
local io = io
local next = next
local pairs = pairs
local table = table
local string = string
local assert = assert
local unpack = unpack
local tonumber = tonumber
local setmetatable = setmetatable

--- Client module.
-- @module client
local client = {}
client.__index = client

--- Instantiate a client object.
-- @param config Client config.
local function new(config)
    local self = {
        config = config,
    }
    return setmetatable(self, client)
end

--- Get status code for an specific error.
-- @param err Error to get status code for.
-- @return Status code.
function client.status_code(err)
    local error_codes = {
        ['(3: Host not found)'] = ngx.HTTP_GONE,
        ['(110: Operation timed out)'] = ngx.HTTP_REQUEST_TIMEOUT,
        ['timeout'] = ngx.HTTP_REQUEST_TIMEOUT,
    }

    -- Default: HTTP 404 not found
    local error_code = ngx.HTTP_NOT_FOUND

    for k, v in pairs(error_codes) do
        if err:sub(-#k) == k then
            error_code = v
            break
        end
    end

    return error_code
end

--- Check if the response is valid
-- @param res The response.
-- @return true if valid, otherwise nil with status code and error
function client:is_valid_response(res)
    if next(self.config.allowed_mime_types) ~= nil and
            not self.config.allowed_mime_types[res.headers['Content-Type']] then
        local supported_images = {}
        for k, _ in pairs(self.config.allowed_mime_types) do
            supported_images[#supported_images + 1] = k
        end

        local error_template = [[The request image is not a valid (supported) image.
Allowed mime types: %s]]

        return nil, {
            status = ngx.HTTP_BAD_REQUEST,
            message = string.format(error_template, table.concat(supported_images, ", "))
        }
    end

    if self.config.max_image_size ~= 0 then
        local length = tonumber(res.headers['Content-Length'])

        if length ~= nil and length > self.config.max_image_size then
            local error_template = [[The image is too big to be downloaded.
Image size: %s
Max image size: %s]]

            return nil, {
                status = ngx.HTTP_BAD_REQUEST,
                message = string.format(error_template, utils.format_bytes(length),
                    utils.format_bytes(self.config.max_image_size))
            }
        end
    end

    if res.status ~= ngx.HTTP_OK then
        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = 'The requested URL returned error: ' .. res.status
        }
    end

    return true, nil
end

--- Download content to a file.
-- @param uri The URI.
-- @param addl_headers Additional headers to add.
-- @param redirect_nr Count how many redirects we've followed.
-- @return File name.
function client:request(uri, addl_headers, redirect_nr)
    addl_headers = addl_headers or {}
    redirect_nr = redirect_nr or 1

    if redirect_nr > self.config.max_redirects then
        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = string.format("Will not follow more than %d redirects", self.config.max_redirects)
        }
    end

    local params = {
        headers = {
            ['User-Agent'] = self.config.user_agent
        },
        path = '',
        query = '',
    }

    for k, v in pairs(addl_headers) do
        params.headers[k] = v
    end

    local parsed_uri, uri_err = utils.parse_uri(uri)
    if not parsed_uri then
        return nil, {
            status = ngx.HTTP_BAD_REQUEST,
            message = uri_err
        }
    end

    local scheme, host, port, path, query = unpack(parsed_uri)
    params.path = path
    params.query = query

    local httpc = http.new()
    httpc:set_timeouts(self.config.timeouts.connect, self.config.timeouts.send, self.config.timeouts.read)

    local c, connect_err = httpc:connect(host, port)

    if not c then
        return nil, {
            status = self.status_code(connect_err),
            message = connect_err
        }
    end

    if scheme == 'https' then
        local ok, handsake_err = httpc:ssl_handshake(nil, host, false)
        if not ok then
            ngx.log(ngx.ERR, 'Failed to do SSL handshake', handsake_err)

            return nil, {
                status = ngx.HTTP_NOT_FOUND,
                message = 'Failed to do SSL handshake.',
            }
        end
    end

    local res, request_err = httpc:request(params)
    if not res then
        -- Always close the socket, otherwise it results in a `unread data
        -- in buffer` error from set_keepalive for the next request.
        httpc:close()

        return nil, {
            status = self.status_code(request_err),
            message = request_err
        }
    end

    if res.status >= 300 and res.status <= 308 and res.headers['Location'] ~= nil then
        httpc:close()

        if query and query ~= "" then
            path = path .. "?" .. query
        end

        local referer = {
            ['Referer'] = scheme .. '://' .. host .. path
        }

        -- recursive call
        return self:request(res.headers['Location'], referer, redirect_nr + 1)
    end

    local valid, invalid_err = self:is_valid_response(res)
    if not valid then
        httpc:close()

        return nil, invalid_err
    end

    -- Now we can use the body_reader iterator,
    -- to stream the body according to our desired chunk size.
    local reader = res.body_reader

    if not reader then
        httpc:close()

        -- Most likely HEAD or 204 etc.
        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = 'No body to be read.',
        }
    end

    -- Create a unique file (starting with 'imo_') in our shared memory.
    res.tmpfile = utils.tempname('/dev/shm', 'imo_')
    if not res.tmpfile then
        httpc:close()

        ngx.log(ngx.ERR, 'Unable to generate a unique file.')

        return nil, {
            status = ngx.HTTP_INTERNAL_SERVER_ERROR,
            message = 'Unable to generate a unique file.',
        }
    end

    local f = assert(io.open(res.tmpfile, 'wb'))

    repeat
        local chunk, read_err = reader(8192)
        if read_err then
            ngx.log(ngx.ERR, read_err)
            break
        end

        if chunk then
            f:write(chunk)
        end
    until not chunk
    f:close()

    local ok, keepalive_err = httpc:set_keepalive()
    if not ok then
        ngx.log(ngx.ERR, 'Failed to set keepalive', keepalive_err)
        os.remove(res.tmpfile)

        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = 'Failed to set keepalive.',
        }
    end

    return res, nil
end

return {
    new = new,
    __object = client
}