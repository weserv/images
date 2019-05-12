--- Helper method to send a HTTP response to the client.
-- @usage
-- local response = require "weserv.helpers.response"
--
-- return response.send_HTTP_NOT_FOUND("The requested URL returned error: 404")
--
-- -- Raw send() helper:
-- return response.send(418, "This is a teapot")
local type = type
local pairs = pairs
local tostring = tostring
local ngx = ngx
local ngx_log = ngx.log
local ngx_ERR = ngx.ERR
local ngx_header = ngx.header
local ngx_print = ngx.print
local ngx_time = ngx.time
local ngx_http_time = ngx.http_time
local ngx_exit = ngx.exit
local HTTP_OK_CODE = ngx.HTTP_OK
local HTTP_GONE_CODE = ngx.HTTP_GONE
local HTTP_NOT_FOUND_CODE = ngx.HTTP_NOT_FOUND
local HTTP_BAD_REQUEST_CODE = ngx.HTTP_BAD_REQUEST
local HTTP_INTERNAL_SERVER_ERROR_CODE = ngx.HTTP_INTERNAL_SERVER_ERROR
local HTTP_TOO_MANY_REQUESTS_CODE = ngx.HTTP_TOO_MANY_REQUESTS
local error_template = "Error %d: Server couldn't parse the ?url= that you were looking for, %s"

--- Define the most common HTTP status codes for sugar methods.
-- Each of those status will generate a helper method (sugar)
-- attached to this exported module prefixed with `send_`.
-- Final signature of those methods will be `send_<status_code_key>(message, headers)`.
-- See @{send} for more details on those parameters.
-- @field HTTP_OK 200 OK
-- @field HTTP_BAD_REQUEST 400 Bad Request
-- @field HTTP_NOT_FOUND 404 Not Found
-- @field HTTP_GONE 410 Gone
-- @field HTTP_TOO_MANY_REQUESTS 429 Too Many Requests
-- @field HTTP_INTERNAL_SERVER_ERROR 500 Internal Server Error
-- @usage return response.send_HTTP_OK()
-- @usage return response.send_HTTP_INTERNAL_SERVER_ERROR()
-- @table status_codes
local response = {
    status_codes = {
        HTTP_OK = HTTP_OK_CODE,
        HTTP_BAD_REQUEST = HTTP_BAD_REQUEST_CODE,
        HTTP_NOT_FOUND = HTTP_NOT_FOUND_CODE,
        HTTP_GONE = HTTP_GONE_CODE,
        HTTP_TOO_MANY_REQUESTS = HTTP_TOO_MANY_REQUESTS_CODE,
        HTTP_INTERNAL_SERVER_ERROR = HTTP_INTERNAL_SERVER_ERROR_CODE,
    }
}

--- Define some default response bodies for some status codes.
-- @field HTTP_BAD_REQUEST_CODE Default: [error_template] .. "error it got: 400 Bad Request"
-- @field HTTP_NOT_FOUND_CODE Default: [error_template] .. "error it got: 404 Not Found"
-- @field HTTP_GONE_CODE Always [error_template] .. "the hostname of the origin is unresolvable (DNS) ..."
-- @field HTTP_TOO_MANY_REQUESTS_CODE Always "429 Too Many Requests - ..."
-- @field HTTP_INTERNAL_SERVER_ERROR Always "An unexpected error occurred"
local response_default_content = {
    [HTTP_BAD_REQUEST_CODE] = function(content)
        return error_template:format(HTTP_NOT_FOUND_CODE, "error it got: " .. (content or "400 Bad Request"))
    end,
    [HTTP_NOT_FOUND_CODE] = function(content)
        return error_template:format(HTTP_NOT_FOUND_CODE, "error it got: " .. (content or "404 Not Found"))
    end,
    [HTTP_GONE_CODE] = function(_)
        return error_template:format(HTTP_GONE_CODE,
            "because the hostname of the origin is unresolvable (DNS) or blocked by policy.")
    end,
    [HTTP_TOO_MANY_REQUESTS_CODE] = function(_)
        return "429 Too Many Requests - There are an unusual number of requests coming from this IP address."
    end,
    [HTTP_INTERNAL_SERVER_ERROR_CODE] = function(_)
        local error_message = [[Something's wrong!
It looks as though we've broken something on our system.
Don't panic, we are fixing it! Please come back in a while..]]

        return error_message
    end,
}

--- Return a closure which will be usable to respond with a certain status code.
-- @local
-- @param status_code The status for which to define a function
local function send_response(status_code)
    -- Send a response for the closure's status code with the given content.
    -- If the content happens to be an error (500), it will be logged by ngx.log as an ERR.
    -- @param content (Optional) The content to send as a response.
    -- @return ngx.exit (Exit current context)
    return function(content, headers)
        if status_code == HTTP_INTERNAL_SERVER_ERROR_CODE and content ~= nil then
            ngx_log(ngx_ERR, tostring(content))
        end

        ngx.status = status_code

        if headers then
            for k, v in pairs(headers) do
                ngx_header[k] = v
            end
        end

        ngx_header["X-Images-Api"] = "4"

        if type(response_default_content[status_code]) == "function" then
            content = response_default_content[status_code](content)
        end

        if status_code == HTTP_OK_CODE then
            -- Only set Cache-Control and Expires headers on non-error responses
            local max_age = 60 * 60 * 24 * 31 -- 31 days
            ngx_header["Expires"] = ngx_http_time(ngx_time() + max_age)
            ngx_header["Cache-Control"] = "max-age=" .. max_age
        else
            -- Error messages are always plain text
            ngx_header["Content-Type"] = "text/plain"
        end

        if content then
            ngx_header["Content-Length"] = #content
            ngx_print(content)
        else
            ngx_header["Content-Length"] = 0
        end

        return ngx_exit(status_code)
    end
end

-- Generate sugar methods (closures) for the most used HTTP status codes.
for status_code_name, status_code in pairs(response.status_codes) do
    response["send_" .. status_code_name] = send_response(status_code)
end

local closure_cache = {}

--- Send a response with any status code or body,
-- Not all status codes are available as sugar methods, this function can be used to send any response.
-- For `status_code=5xx` the `content` parameter should be the description of the error that occurred.
-- For `status_code=500` the content will be logged by ngx.log as an ERR.
-- Will call `ngx.print` and `ngx.exit`, terminating the current context.
-- @see ngx.print
-- @see ngx.exit
-- @param status_code HTTP status code to send
-- @param body A string which will be the body of the sent response.
-- @param headers Response headers to send.
-- @return ngx.exit (Exit current context)
function response.send(status_code, body, headers)
    local res = closure_cache[status_code]
    if not res then
        res = send_response(status_code)
        closure_cache[status_code] = res
    end

    return res(body, headers)
end

return response