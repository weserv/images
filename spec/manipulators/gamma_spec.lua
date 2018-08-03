local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("gamma manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.gamma"
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

    it("test resolve gamma", function()
        assert.equal(1.5, manipulator.resolve_gamma("1.5"))
        assert.equal(2.2, manipulator.resolve_gamma(nil))
        assert.equal(2.2, manipulator.resolve_gamma("a"))
        assert.equal(2.2, manipulator.resolve_gamma(".1"))
        assert.equal(2.2, manipulator.resolve_gamma("3.999"))
        assert.equal(2.2, manipulator.resolve_gamma("0.005"))
        assert.equal(2.2, manipulator.resolve_gamma("-1"))
    end)

    describe("test gamma", function()
        it("default value", function()
            local test_image = fixtures.input_jpg_with_gamma_holiness
            local expected_image = fixtures.expected_dir .. "/gamma-2.2.jpg"
            -- Above q=90, libvips will write 4:4:4, ie. no subsampling of Cr and Cb
            local params = {
                gam = "true",
                q = "95"
            }

            local image = api:process(test_image, params)

            assert.equal(258, image:width())
            assert.equal(222, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("value of 3", function()
            local test_image = fixtures.input_jpg_with_gamma_holiness
            local expected_image = fixtures.expected_dir .. "/gamma-3.0.jpg"
            -- Above q=90, libvips will write 4:4:4, ie. no subsampling of Cr and Cb
            local params = {
                gam = "3",
                q = "95"
            }

            local image = api:process(test_image, params)

            assert.equal(258, image:width())
            assert.equal(222, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_overlay_layer1
            local expected_image = fixtures.expected_dir .. "/gamma-alpha.png"
            -- Above q=90, libvips will write 4:4:4, ie. no subsampling of Cr and Cb
            local params = {
                w = "320",
                gam = "true",
                q = "95"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.similar_image(expected_image, image)
        end)
    end)
end)