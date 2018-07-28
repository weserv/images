local utils = require "weserv.helpers.utils"

describe("api", function()
    local old_ngx = _G.ngx
    local api

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

        local weserv_api = require "weserv.api"
        api = weserv_api.new()
    end)

    teardown(function()
        _G.ngx = old_ngx
    end)

    after_each(function()
        -- Clear logs and manipulators after each test
        _G.ngx._logs = {}
        api:clear_manipulators()
    end)

    it("test get load options", function()
        local params = {
            access_method = 'sequential',
            page = '0'
        }

        params.tmp_file_name = 'test.pdf'
        params.loader = 'VipsForeignLoadPdfFile'
        local load_options, string_options = api.get_load_options(params)
        assert.are.same({
            access = 'sequential',
            page = 0
        }, load_options)
        assert.equal('[page=0]', string_options)

        params.tmp_file_name = 'test.tiff'
        params.loader = 'VipsForeignLoadTiffFile'
        load_options, string_options = api.get_load_options(params)
        assert.are.same({
            access = 'sequential',
            page = 0
        }, load_options)
        assert.equal('[page=0]', string_options)

        params.tmp_file_name = 'test.ico'
        params.loader = 'VipsForeignLoadMagickFile'
        load_options, string_options = api.get_load_options(params, 'ico')
        assert.are.same({
            access = 'sequential',
            page = 0
        }, load_options)
        assert.equal('[page=0]', string_options)

        params.tmp_file_name = 'test.jpg'
        params.loader = 'VipsForeignLoadJpegFile'
        load_options, string_options = api.get_load_options(params)
        assert.are.same({
            access = 'sequential',
        }, load_options)
        assert.equal('', string_options)
    end)

    describe("test add manipulators", function()
        it("all", function()
            local manipulators = {
                require "weserv.manipulators.trim",
                require "weserv.manipulators.thumbnail",
                require "weserv.manipulators.orientation",
                require "weserv.manipulators.crop",
                require "weserv.manipulators.letterbox",
                require "weserv.manipulators.brightness",
                require "weserv.manipulators.contrast",
                require "weserv.manipulators.gamma",
                require "weserv.manipulators.sharpen",
                require "weserv.manipulators.filter",
                require "weserv.manipulators.blur",
                require "weserv.manipulators.background",
                require "weserv.manipulators.mask"
            }

            api:add_manipulators(manipulators)
            assert.equal(#manipulators, #api.manipulators)
        end)

        it("invalid", function()
            api:add_manipulator(function() end)
            assert.are.same({}, api.manipulators)

            api:add_manipulators({
                function() end,
                {
                    process = 'foo bar'
                }
            })
            assert.are.same({}, api.manipulators)
        end)
    end)

    it("test process without any manipulator", function()
        local image, api_err = api:process('noimage.jpg', { url = 'http://example.org/noimage.jpg' })
        assert.falsy(image)
        assert.equal(500, api_err.status)
        assert.equal('Attempted to run images.weserv.nl without any manipulator(s).', api_err.message)
    end)

    describe("test process", function()
        it("invalid or unsupported", function()
            api:add_manipulator(require "weserv.manipulators.thumbnail")

            local test_image = './spec/fixtures/does-not-exists.jpg'

            local image, api_err = api:process(test_image, {
                url = 'http://example.org/does-not-exists.jpg'
            })
            assert.falsy(image)
            assert.equal(404, api_err.status)
            assert.equal('Invalid or unsupported image format. Is it a valid image?', api_err.message)

            -- Log invalid or unsupported errors
            assert.equal(1, #ngx._logs)
        end)

        it("not readable", function()
            api:add_manipulator(require "weserv.manipulators.thumbnail")

            -- Create a unique file (starting with 'imo_') in our shared memory.
            local tmpfile = utils.tempname('/dev/shm', 'imo_')

            -- Write a non readable image to the unique file
            local f = assert(io.open(tmpfile, 'w'))
            f:write('GIF89a')
            f:close()

            local image, api_err = api:process(tmpfile, {
                url = 'http://example.org/invalid.gif'
            })
            assert.falsy(image)
            assert.equal(404, api_err.status)
            assert.equal('Image not readable. Is it a valid image?', api_err.message)
            assert(os.remove(tmpfile))

            -- Log not readable errors
            assert.equal(1, #ngx._logs)
        end)
    end)
end)