local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("contrast manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.contrast"
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

    it("test resolve contrast", function()
        assert.equal(50, manipulator.resolve_contrast('50'))
        assert.equal(0, manipulator.resolve_contrast(nil))
        assert.equal(0, manipulator.resolve_contrast('101'))
        assert.equal(0, manipulator.resolve_contrast('-101'))
        assert.equal(0, manipulator.resolve_contrast('a'))
    end)

    describe("test contrast", function()
        it("increase", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/contrast-increase.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                con = '30'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("decrease", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/contrast-decrease.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                con = '-30'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)