local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"
local utils = require "weserv.helpers.utils"

describe("trim manipulator", function()
    local old_ngx = _G.ngx
    local api, manipulator

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

        manipulator = require "weserv.manipulators.trim"
        api = weserv_api.new()
        api:add_manipulators({
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
        })
    end)

    teardown(function()
        _G.ngx = old_ngx
    end)

    after_each(function()
        -- Clear logs after each test
        _G.ngx._logs = {}
    end)

    it("test resolve trim", function()
        assert.equal(50, manipulator.resolve_trim("50"))
        assert.equal(50.50, manipulator.resolve_trim("50.50"))
        assert.equal(10, manipulator.resolve_trim(nil))
        assert.equal(10, manipulator.resolve_trim("a"))
        assert.equal(10, manipulator.resolve_trim("-1"))
        assert.equal(10, manipulator.resolve_trim("256"))
    end)

    describe("test trim", function()
        it("threshold 25", function()
            local test_image = fixtures.input_png_overlay_layer1
            local expected_image = fixtures.expected_dir .. "/alpha-layer-1-fill-trim-resize.png"
            local params = {
                w = "450",
                h = "322",
                t = "square",
                trim = "25"
            }

            local image = api:process(test_image, params)

            assert.equal(450, image:width())
            assert.equal(322, image:height())
            assert.True(utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("16bit with transparency", function()
            local test_image = fixtures.input_png_with_transparency_16bit
            local expected_image = fixtures.expected_dir .. "/trim-16bit-rgba.png"
            local params = {
                w = "32",
                h = "32",
                t = "square",
                trim = "10"
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(32, image:width())
            assert.equal(32, image:height())
            assert.True(utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("skip shrink-on-load", function()
            local test_image = fixtures.input_jpg_overlay_layer2
            local expected_image = fixtures.expected_dir .. "/alpha-layer-2-trim-resize.jpg"
            local params = {
                w = "300",
                trim = "10"
            }

            local image = api:process(test_image, params)

            assert.equal(300, image:width())
            assert.equal(300, image:height())
            assert.False(utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("aggressive trim returns original image", function()
            local test_image = fixtures.input_png_overlay_layer0
            local params = {
                trim = "200"
            }

            local image = api:process(test_image, params)

            -- Log threshold errors
            assert.equal(1, #ngx._logs)

            -- We could use shrink-on-load for the next thumbnail manipulator
            assert.falsy(params.trim)

            -- Check if dimensions are unchanged
            assert.equal(2048, image:width())
            assert.equal(1536, image:height())

            -- Check if the image is unchanged
            assert.similar_image(test_image, image)
        end)
    end)
end)