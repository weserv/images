local weserv_api = require "weserv.api"
local weserv_utils = require "weserv.helpers.utils"
local fixtures = require "spec.fixtures"

describe("filter manipulator", function()
    local api

    setup(function()
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

    it("test greyscale filter", function()
        local test_image = fixtures.input_jpg
        local expected_image = fixtures.expected_dir .. "/greyscale.jpg"
        local params = {
            w = "320",
            h = "240",
            t = "square",
            filt = "greyscale"
        }

        local image = api:process(test_image, params)

        assert.equal(1, image:bands());
        assert.equal(320, image:width())
        assert.equal(240, image:height())
        assert.similar_image(expected_image, image)
    end)

    describe("test sepia filter", function()
        it("jpeg", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/sepia.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "sepia"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_overlay_layer1
            local expected_image = fixtures.expected_dir .. "/sepia-trans.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "sepia"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)
    end)

    describe("test negate filter", function()
        it("jpeg", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/negate.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png", function()
            local test_image = fixtures.input_png
            local expected_image = fixtures.expected_dir .. "/negate.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/negate-trans.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("png with grey alpha", function()
            local test_image = fixtures.input_png_with_grey_alpha
            local expected_image = fixtures.expected_dir .. "/negate-alpha.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("webp", function()
            local test_image = fixtures.input_webp
            local expected_image = fixtures.expected_dir .. "/negate.webp"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("webp transparent", function()
            local test_image = fixtures.input_webp_with_transparency
            local expected_image = fixtures.expected_dir .. "/negate-trans.webp"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "negate"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)
    end)
end)