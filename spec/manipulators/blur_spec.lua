local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("blur manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.blur"
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

    it("test resolve blur", function()
        assert.equal(50, manipulator.resolve_blur('50'))
        assert.equal(-1.0, manipulator.resolve_blur(nil))
        assert.equal(-1.0, manipulator.resolve_blur('a'))
        assert.equal(-1.0, manipulator.resolve_blur('-1'))
        assert.equal(-1.0, manipulator.resolve_blur('1001'))
    end)

    describe("test blur", function()
        it("radius 1", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/blur-1.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                blur = '1'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("radius 10", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/blur-10.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                blur = '10'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("radius 0.3", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/blur-0.3.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                blur = '0.3'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("mild", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/blur-mild.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                blur = 'true'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_overlay_layer1
            local expected_image = fixtures.expected_dir .. '/blur-trans.png'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                blur = '10'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)