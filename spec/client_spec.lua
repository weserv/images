local match = require "luassert.match"

-- luacheck: globals ngx._logs
describe("client", function()
    local old_ngx = _G.ngx
    local snapshot
    local stubbed_ngx
    local old_http = package.loaded["resty.http"]
    local old_utils = package.loaded["weserv.helpers.utils"]
    local stubbed_http
    local client

    local default_config = {
        user_agent = "Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)",
        timeouts = {
            connect = 5000,
            send = 5000,
            read = 10000,
        },
        max_image_size = 24,
        max_redirects = 10,
        allowed_mime_types = {}
    }

    local errors = {}

    local body_reader = function(file_chunks)
        return coroutine.wrap(function()
            for i = 1, file_chunks do
                coroutine.yield("Chunk " .. i .. "\n")
            end
            coroutine.yield(nil)
        end)
    end


    local response = {
        status = 200,
        headers = {},
        body_reader = body_reader(3)
    }

    local tempname = true

    setup(function()
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
    end)

    before_each(function()
        snapshot = assert:snapshot()
        stubbed_ngx = {
            _logs = {},
        }
        stubbed_ngx.log = function(...)
            stubbed_ngx._logs[#stubbed_ngx._logs + 1] = table.concat({ ... }, " ")
        end

        -- Busted requires explicit _G to access the global environment
        _G.ngx = setmetatable(stubbed_ngx, { __index = old_ngx })

        client = require("weserv.client").new(default_config)
    end)

    after_each(function()
        snapshot:revert()
        _G.ngx = old_ngx
    end)

    teardown(function()
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
            body_reader = body_reader(3)
        }
    end)

    it("test config", function()
        assert.are.same(default_config, client.config)
    end)

    describe("test request", function()
        it("connects to host and writes to file", function()
            local res, _ = client:request("https://ory.weserv.nl/lichtenstein.jpg?foo=bar")

            assert.spy(stubbed_http.set_timeouts).was_called_with(match._, default_config.timeouts.connect,
                default_config.timeouts.send, default_config.timeouts.read)
            assert.spy(stubbed_http.connect).was_called_with(match._, "ory.weserv.nl", 443)
            assert.spy(stubbed_http.ssl_handshake).was_called_with(match._, nil, "ory.weserv.nl", false)
            assert.spy(stubbed_http.request).was_called_with(match._, match.is_same({
                headers = {
                    ["User-Agent"] = default_config.user_agent
                },
                path = "/lichtenstein.jpg",
                query = "foo=bar",
            }))
            assert.spy(stubbed_http.set_keepalive).was.called()

            local tmpfile_start = "/dev/shm/imo_"
            assert.True(res.tmpfile:sub(1, #tmpfile_start) == tmpfile_start)

            local f = assert(io.open(res.tmpfile, "r"))
            local t = f:read("*all")
            f:close()

            assert.equal("Chunk 1\nChunk 2\nChunk 3\n", t)
            assert(os.remove(res.tmpfile))
        end)

        it("invalid uri", function()
            local res, err = client:request("http:\\foobar")

            assert.equal(400, err.status)
            assert.equal("Unable to parse URL", err.message)
            assert.falsy(res)

            -- Don't log invalid uris
            assert.equal(0, #ngx._logs)
        end)

        it("connect error", function()
            errors.connect = "timeout"

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg?foo=bar")

            assert.equal(408, err.status)
            assert.equal("timeout", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was_not_called()
            assert.spy(stubbed_http.request).was_not_called()
            assert.spy(stubbed_http.set_keepalive).was_not_called()

            -- Don't log connect errors
            assert.equal(0, #ngx._logs)
        end)

        it("ssl handshake error", function()
            errors.ssl_handshake = "timeout"

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg?foo=bar")

            assert.equal(404, err.status)
            assert.equal("Failed to do SSL handshake.", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was.called()
            assert.spy(stubbed_http.request).was_not.called()
            assert.spy(stubbed_http.set_keepalive).was_not.called()

            assert.spy(stubbed_http.close).was.called()

            -- Log ssl handshake errors
            assert.equal(1, #ngx._logs)
        end)

        it("request error", function()
            errors.request = "timeout"

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg?foo=bar")

            assert.equal(408, err.status)
            assert.equal("timeout", err.message)
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

        it("max image size error", function()
            response.body_reader = body_reader(4)

            local valid, invalid_err = client:request("https://ory.weserv.nl/big_image.jpg")

            assert.spy(stubbed_http.connect).was.called()
            assert.spy(stubbed_http.ssl_handshake).was.called()
            assert.spy(stubbed_http.request).was.called()
            assert.spy(stubbed_http.set_keepalive).was_not_called()

            assert.falsy(valid)
            assert.equal(404, invalid_err.status)
            assert.equal([[The image is too big to be downloaded. Max image size: 24 B]], invalid_err.message)

            -- Log images that are too big
            assert.equal(1, #ngx._logs)
        end)

        it("keepalive error", function()
            errors.keepalive = "timeout"

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg?foo=bar")

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
            -- Make sure that we unescape the redirect URI.
            -- See: https://github.com/weserv/images/issues/142
            local redirect = "https://ory.weserv.nl/%252A/image2.jpg?foo=bar"

            response.status = 302
            response.headers["Location"] = redirect

            local res, err = client:request("ory.weserv.nl/%2A/image.jpg?foo=bar")

            assert.equal(404, err.status)
            assert.equal(string.format("Will not follow more than %d redirects", default_config.max_redirects),
                err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.request).was.called(10)
            assert.spy(stubbed_http.request).was.called_with(match._, match.contains({
                headers = {
                    ["User-Agent"] = default_config.user_agent
                },
                path = "/%2A/image.jpg",
                query = "foo=bar",
            }))
            assert.spy(stubbed_http.request).was.called_with(match._, match.contains({
                headers = {
                    ["User-Agent"] = default_config.user_agent,
                    -- Referer needs to be added
                    ["Referer"] = "http://ory.weserv.nl/%2A/image.jpg?foo=bar"
                },
                path = "/%2A/image2.jpg",
                query = "foo=bar",
            }))
            assert.spy(stubbed_http.close).was.called()
        end)

        it("non 200 status", function()
            response.status = 500

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg")

            assert.equal(404, err.status)
            assert.equal("The requested URL returned error: 500", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.close).was.called()
        end)

        it("no body", function()
            response.body_reader = nil

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg")

            assert.equal(404, err.status)
            assert.equal("No body to be read.", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.close).was.called()
        end)

        it("generate unique file error", function()
            tempname = false

            local res, err = client:request("https://ory.weserv.nl/lichtenstein.jpg")

            assert.equal(500, err.status)
            assert.equal("Unable to generate a unique file.", err.message)
            assert.falsy(res)

            assert.spy(stubbed_http.close).was.called()

            -- Log unique file errors
            assert.equal(1, #ngx._logs)
        end)

        it("read error", function()
            response.body_reader = function(_)
                return nil, "timeout"
            end

            local res, _ = client:request("https://ory.weserv.nl/lichtenstein.jpg")

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
                user_agent = "Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)",
                timeouts = {
                    connect = 5000,
                    send = 5000,
                    read = 10000,
                },
                max_image_size = 0,
                max_redirects = 10,
                allowed_mime_types = {
                    ["image/jpeg"] = "jpg",
                }
            })
            local valid, invalid_err = new_client:is_valid_response({
                headers = {
                    ["Content-Type"] = "image/png"
                },
            })

            assert.falsy(valid)
            assert.equal(404, invalid_err.status)
            assert.equal([[The request image is not a valid (supported) image.
Allowed mime types: image/jpeg]], invalid_err.message)
        end)
    end)
end)