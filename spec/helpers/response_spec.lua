-- luacheck: globals ngx._body ngx._logs ngx._exitcode
describe("utils", function()
    local old_ngx = _G.ngx
    local snapshot
    local stubbed_ngx
    local response

    before_each(function()
        snapshot = assert:snapshot()
        stubbed_ngx = {
            _body = "",
            _logs = {},
            _exitcode = nil,
            header = {},
        }
        stubbed_ngx.log = function(...)
            stubbed_ngx._logs[#stubbed_ngx._logs + 1] = table.concat({ ... }, " ")
        end
        stubbed_ngx.print = function(s)
            stubbed_ngx._body = stubbed_ngx._body .. s
        end
        stubbed_ngx.exit = function(code)
            stubbed_ngx._exitcode = code
        end

        -- Busted requires explicit _G to access the global environment
        _G.ngx = setmetatable(stubbed_ngx, { __index = old_ngx })

        -- Reinitialize the utils package
        package.loaded["weserv.helpers.response"] = nil
        response = require "weserv.helpers.response"
    end)

    after_each(function()
        snapshot:revert()
        _G.ngx = old_ngx
    end)

    it("has a list of the main http status codes", function()
        assert.is_table(response.status_codes)
    end)

    it("is callable via `send_HTTP_STATUS_CODE`", function()
        for status_code_name, _ in pairs(response.status_codes) do
            assert.has_no.errors(function()
                response["send_" .. status_code_name]()
            end)
        end
    end)

    it("sets the correct ngx values and call ngx.say and ngx.exit", function()
        response.send_HTTP_OK("OK", {
            ["Content-Type"] = "text/plain"
        })
        assert.equal(ngx.status, response.status_codes.HTTP_OK)
        assert.equal("4", ngx.header["X-Images-Api"])
        assert.equal("text/plain", ngx.header["Content-Type"])
        assert.equal("OK", ngx._body)
        assert.equal(200, ngx._exitcode)
    end)

    it("calls `ngx.exit` with the corresponding status_code", function()
        for status_code_name, status_code in pairs(response.status_codes) do
            assert.has_no.errors(function()
                response["send_" .. status_code_name]()
                assert.equal(status_code, ngx._exitcode)
            end)
        end
    end)

    it("calls `ngx.log` if 500 status code was given", function()
        response.send_HTTP_BAD_REQUEST()
        assert.equal(0, #ngx._logs)

        response.send_HTTP_BAD_REQUEST("error")
        assert.equal(0, #ngx._logs)

        response.send_HTTP_INTERNAL_SERVER_ERROR()
        assert.equal(0, #ngx._logs)

        response.send_HTTP_INTERNAL_SERVER_ERROR("error")
        assert.equal(1, #ngx._logs)
    end)

    describe("default content rules for some status codes", function()
        it("should apply default content rules for some status codes", function()
            response.send_HTTP_BAD_REQUEST()
            assert.truthy(ngx._body:find("400 Bad Request"))
            response.send_HTTP_NOT_FOUND("override")
            assert.truthy(ngx._body:find("override"))
        end)
        it("should apply default content rules for some status codes", function()
            response.send_HTTP_NOT_FOUND()
            assert.truthy(ngx._body:find("404 Not Found"))
            response.send_HTTP_NOT_FOUND("override")
            assert.truthy(ngx._body:find("override"))
        end)
        it("should apply default content rules for some status codes", function()
            response.send_HTTP_GONE()
            assert.truthy(ngx._body:find("the hostname of the origin is unresolvable"))
            response.send_HTTP_GONE("override")
            assert.truthy(ngx._body:find("the hostname of the origin is unresolvable"))
        end)
        it("should apply default content rules for some status codes", function()
            response.send_HTTP_TOO_MANY_REQUESTS()
            assert.truthy(ngx._body:find("429 Too Many Requests"))
            response.send_HTTP_TOO_MANY_REQUESTS("override")
            assert.truthy(ngx._body:find("429 Too Many Requests"))
        end)
        it("should apply default content rules for some status codes", function()
            response.send_HTTP_INTERNAL_SERVER_ERROR()
            assert.truthy(ngx._body:find("Something's wrong!"))
            response.send_HTTP_INTERNAL_SERVER_ERROR("override")
            assert.truthy(ngx._body:find("Something's wrong!"))
        end)
    end)

    describe("content-length header", function()
        it("is set", function()
            response.send_HTTP_OK("OK")
            assert.equal(2, tonumber(ngx.header["Content-Length"]))
        end)

        it("is set to 0 when no content", function()
            response.send_HTTP_OK()
            assert.equal(0, tonumber(ngx.header["Content-Length"]))
        end)
    end)

    describe("send()", function()
        it("sends a custom status code", function()
            response.send(418, "This is a teapot")
            assert.equal("This is a teapot", ngx._body)
            assert.equal(418, ngx._exitcode)

            response.send(501)
            assert.equal(501, ngx._exitcode)
        end)
    end)
end)