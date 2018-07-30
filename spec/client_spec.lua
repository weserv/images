local match = require "luassert.match"

describe("client", function()
    local old_ngx = _G.ngx
    local old_http = package.loaded["resty.http"]
    local old_utils = package.loaded["weserv.helpers.utils"]
    local stubbed_http
    local client

    local default_config = {
        user_agent = 'Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)',
        timeouts = {
            connect = 5000,
            send = 5000,
            read = 10000,
        },
        max_image_size = 0,
        max_redirects = 10,
        allowed_mime_types = {}
    }

    local errors = {}

    local reader_co = coroutine.create(function(max_chunk_size)
        coroutine.yield('Chunk 1 ' .. max_chunk_size .. "\n")
        coroutine.yield('Chunk 2 ' .. max_chunk_size .. "\n")
        coroutine.yield('Chunk 3 ' .. max_chunk_size .. "\n")
        coroutine.yield(nil)
    end)

    local body_reader = function(...)
        if coroutine.status(reader_co) == "suspended" then
            return select(2, coroutine.resume(reader_co, ...))
        else
            return nil, "can't resume a " .. coroutine.status(reader_co) .. " coroutine"
        end
    end

    local response = {
        status = 200,
        headers = {},
        body_reader = body_reader
    }

    local tempname = true

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
            set_timeouts = function(_, _, _, _) end,
            connect = function(_, _, _)
                if errors.connect ~= nil then
                    return nil, errors.connect
                end

                return 1, nil
            end,
            ssl_handshake = function(_, _, _, _)
                if errors.ssl_handshake ~= nil then
                    return nil, errors.ssl_handshake
                end

                return 1, nil
            end,
            request = function(_, _)
                if errors.request ~= nil then
                    return nil, errors.request
                end

                return response, nil
            end,
            set_keepalive = function(_)
                if errors.keepalive ~= nil then
                    return nil, errors.keepalive
                end

                return 1, nil
            end,
            close = function(_) end
        }
        spy.on(stubbed_http, "set_timeouts")
        spy.on(stubbed_http, "connect")
        spy.on(stubbed_http, "ssl_handshake")
        spy.on(stubbed_http, "request")
        spy.on(stubbed_http, "set_keepalive")
        spy.on(stubbed_http, "close")

        package.loaded["resty.http"] = {
            new = function()
                return stubbed_http
            end
        }

        package.loaded["weserv.helpers.utils"] = setmetatable({
            tempname = function(dir, prefix)
                if not tempname then
                    return nil
                end

                return old_utils.tempname(dir, prefix)
            end
        }, { __index = old_utils })

        client = require("weserv.client").new(default_config)
    end)

    teardown(function()
        _G.ngx = old_ngx
        package.loaded["resty.http"] = old_http
        package.loaded["weserv.helpers.utils"] = old_utils
    end)

    after_each(function()
        -- Clear logs, errors, call history and response after each test
        _G.ngx._logs = {}
        tempname = true
        errors = {}
        stubbed_http.set_timeouts:clear()
        stubbed_http.connect:clear()
        stubbed_http.ssl_handshake:clear()
        stubbed_http.request:clear()
        stubbed_http.set_keepalive:clear()
        response = {
            status = 200,
            headers = {},
            body_reader = body_reader
        }
    end)

    it("test config", function()
        assert.are.same(default_config, client.config)
    end)

    describe("test request", function()
        it("connects to host and writes to file", function()
            local res, _ = client:request('https://ory.weserv.nl/lichtenstein.jpg?foo=bar')

            assert.spy(stubbed_http.set_timeouts).was_called_with(match._, default_config.timeouts.connect,
                default_config.timeouts.send, default_config.timeouts.read)
            assert.spy(stubbed_http.connect).was_called_with(match._, 'ory.weserv.nl', 443)
            assert.spy(stubbed_http.ssl_handshake).was_called_with(match._, nil, 'ory.weserv.nl', false)
            assert.spy(stubbed_http.request).was_called_with(match._, match.is_same({
                headers = {
                    ['User-Agent'] = default_config.user_agent
                },
                path = '/lichtenstein.jpg',
                query = 'foo=bar',
            }))
            assert.spy(stubbed_http.set_keepalive).was.called()

            local tmpfile_start = '/dev/shm/imo_'
            assert.True(res.tmpfile:sub(1, #tmpfile_start) == tmpfile_start)

            local f = assert(io.open(res.tmpfile, "r"))
            local t = f:read("*all")
            f:close()

            assert.equal("Chunk 1 8192\nChunk 2 8192\nChunk 3 8192\n", t)
            assert(os.remove(res.tmpfile))
        end)

        it("invalid uri", function()
            local res, err = client:request('http:\\foobar')

            assert.equal(400, err.status)
            assert.equal("Unable to parse URL", err.message)
            assert.falsy(res)

            -- Don't log invalid uris
            assert.equal(0, #ngx._logs)
        end)

        it("connect error", function()
            errors.connect = 'timeout'

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg?foo=bar')

            assert.equal(408, err.status)
            assert.equal('timeout', err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was_not_called()
            assert.spy(stubbed_http.request).was_not_called()
            assert.spy(stubbed_http.set_keepalive).was_not_called()

            -- Don't log connect errors
            assert.equal(0, #ngx._logs)
        end)

        it("ssl handshake error", function()
            errors.ssl_handshake = 'timeout'

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg?foo=bar')

            assert.equal(404, err.status)
            assert.equal('Failed to do SSL handshake.', err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was.called()
            assert.spy(stubbed_http.request).was_not.called()
            assert.spy(stubbed_http.set_keepalive).was_not.called()

            -- Log ssl handshake errors
            assert.equal(1, #ngx._logs)
        end)

        it("request error", function()
            errors.request = 'timeout'

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg?foo=bar')

            assert.equal(408, err.status)
            assert.equal('timeout', err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was.called()
            assert.spy(stubbed_http.request).was.called()
            assert.spy(stubbed_http.set_keepalive).was_not.called()

            -- Make sure to always close the socket, otherwise it results in a `unread data
            -- in buffer` error from set_keepalive for the next request.
            assert.spy(stubbed_http.close).was.called()

            -- Don't log request errors
            assert.equal(0, #ngx._logs)
        end)

        it("keepalive error", function()
            errors.keepalive = 'timeout'

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg?foo=bar')

            assert.equal(404, err.status)
            assert.equal("Failed to set keepalive.", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was.called()
            assert.spy(stubbed_http.request).was.called()
            assert.spy(stubbed_http.set_keepalive).was.called()

            -- Log keepalive errors
            assert.equal(1, #ngx._logs)
        end)

        it("max redirects error", function()
            -- RFC 3986 without the :/?* characters
            local filename_encoded = "%23%5B%20%5D%40%21%24%26%27%28%29%20%2C%3B%3D"
            -- RFC 3986 without the &= characters
            local query_encoded = '%3A%2F%3F%23%5B%20%5D%40%21%24%27%28%29%2A%20%2C%3B'

            local redirect = 'https://ory.weserv.nl/' .. filename_encoded .. '.jpg?foo=' .. query_encoded .. '&bar=foo'

            response.status = 302
            response.headers['Location'] = redirect

            local res, err = client:request("ory.weserv.nl/#[ ]@!$&'()+,;=.jpg?foo=:/?#[ ]@!$'()*+,;&bar=foo")

            assert.equal(404, err.status)
            assert.equal(string.format("Will not follow more than %d redirects", default_config.max_redirects),
                err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.request).was.called(10)
            assert.spy(stubbed_http.request).was.called_with(match._, match.same({
                headers = {
                    ['User-Agent'] = default_config.user_agent
                },
                -- Must be properly encoded
                path = '/' .. filename_encoded .. '.jpg',
                query = 'foo=' .. query_encoded .. '&bar=foo',
            }))
            assert.spy(stubbed_http.request).was.called_with(match._, match.same({
                headers = {
                    ['User-Agent'] = default_config.user_agent,
                    -- Referer needs to be added
                    ['Referer'] = 'http://ory.weserv.nl/' .. filename_encoded .. '.jpg?foo=' ..
                            query_encoded .. '&bar=foo'
                },
                -- Must be properly encoded
                path = '/' .. filename_encoded .. '.jpg',
                query = 'foo=' .. query_encoded .. '&bar=foo',
            }))
            assert.spy(stubbed_http.close).was.called()
        end)

        it("non 200 status", function()
            response.status = 500

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg')

            assert.equal(404, err.status)
            assert.equal("The requested URL returned error: 500", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.close).was.called()
        end)

        it("no body", function()
            response.body_reader = nil

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg')

            assert.equal(404, err.status)
            assert.equal("No body to be read.", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.close).was.called()
        end)

        it("generate unique file error", function()
            tempname = false

            local res, err = client:request('https://ory.weserv.nl/lichtenstein.jpg')

            assert.equal(500, err.status)
            assert.equal("Unable to generate a unique file.", err.message)
            assert.falsy(res)

            -- Log unique file errors
            assert.equal(1, #ngx._logs)

            assert.spy(stubbed_http.close).was.called()
        end)

        it("read error", function()
            response.body_reader = function(_)
                return nil, 'timeout'
            end

            local res, _ = client:request('https://ory.weserv.nl/lichtenstein.jpg')

            -- Log read errors
            assert.equal(1, #ngx._logs)

            local f = assert(io.open(res.tmpfile, "r"))
            local t = f:read("*all")
            f:close()

            -- Empty file
            assert.equal("", t)
            assert(os.remove(res.tmpfile))
        end)
    end)

    describe("test is valid response", function()
        it("allowed mime types", function()
            local new_client = require("weserv.client").new({
                user_agent = 'Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)',
                timeouts = {
                    connect = 5000,
                    send = 5000,
                    read = 10000,
                },
                max_image_size = 0,
                max_redirects = 10,
                allowed_mime_types = {
                    ['image/jpeg'] = 'jpg',
                }
            })
            local valid, invalid_err = new_client:is_valid_response({
                headers = {
                    ['Content-Type'] = 'image/png'
                },
            })

            assert.equal(400, invalid_err.status)
            assert.equal([[The request image is not a valid (supported) image.
Allowed mime types: image/jpeg]], invalid_err.message)
            assert.falsy(valid)
        end)

        it("max image size", function()
            local new_client = require("weserv.client").new({
                user_agent = 'Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)',
                timeouts = {
                    connect = 5000,
                    send = 5000,
                    read = 10000,
                },
                max_image_size = 1024,
                max_redirects = 10,
                allowed_mime_types = {}
            })
            local valid, invalid_err = new_client:is_valid_response({
                headers = {
                    ['Content-Length'] = '2048'
                },
            })

            assert.equal(400, invalid_err.status)
            assert.equal([[The image is too big to be downloaded.
Image size: 2 KB
Max image size: 1 KB]], invalid_err.message)
            assert.falsy(valid)
        end)
    end)
end)