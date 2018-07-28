local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("brightness manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.brightness"
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

    it("test resolve brightness", function()
        assert.equal(50, manipulator.resolve_brightness('50'))
        assert.equal(0, manipulator.resolve_brightness(nil))
        assert.equal(0, manipulator.resolve_brightness('101'))
        assert.equal(0, manipulator.resolve_brightness('-101'))
        assert.equal(0, manipulator.resolve_brightness('a'))
    end)

    describe("test brightness", function()
        it("increase", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/brightness-increase.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                bri = '30'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("decrease", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/brightness-decrease.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                bri = '-30'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_overlay_layer1
            local expected_image = fixtures.expected_dir .. '/brightness-trans.png'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                bri = '30'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)