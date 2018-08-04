local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"
local utils = require "weserv.helpers.utils"

describe("thumbnail manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.thumbnail"
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

    describe("test resolve", function()
        it("dpr", function()
            assert.equal(1.0, manipulator.resolve_dpr("invalid"))
            assert.equal(1.0, manipulator.resolve_dpr("-1"))
            assert.equal(1.0, manipulator.resolve_dpr("9"))
            assert.equal(2.0, manipulator.resolve_dpr("2"))
        end)

        it("dimension", function()
            assert.equal(0, manipulator.resolve_dimension("invalid"))
            assert.equal(0, manipulator.resolve_dimension("-1"))
            assert.equal(0, manipulator.resolve_dimension("0"))
            assert.equal(100, manipulator.resolve_dimension("100"))
        end)

        it("fit", function()
            assert.equal("fit", manipulator.resolve_fit("fit"))
            assert.equal("fitup", manipulator.resolve_fit("fitup"))
            assert.equal("square", manipulator.resolve_fit("square"))
            assert.equal("squaredown", manipulator.resolve_fit("squaredown"))
            assert.equal("absolute", manipulator.resolve_fit("absolute"))
            assert.equal("letterbox", manipulator.resolve_fit("letterbox"))
            assert.equal("crop", manipulator.resolve_fit("crop-top-left"))
            assert.equal("crop", manipulator.resolve_fit("crop-bottom-left"))
            assert.equal("crop", manipulator.resolve_fit("crop-left"))
            assert.equal("crop", manipulator.resolve_fit("crop-top-right"))
            assert.equal("crop", manipulator.resolve_fit("crop-bottom-right"))
            assert.equal("crop", manipulator.resolve_fit("crop-right"))
            assert.equal("crop", manipulator.resolve_fit("crop-top"))
            assert.equal("crop", manipulator.resolve_fit("crop-bottom"))
            assert.equal("crop", manipulator.resolve_fit("crop-center"))
            assert.equal("crop", manipulator.resolve_fit("crop-25-75"))
            assert.equal("crop", manipulator.resolve_fit("crop-0-100"))
            assert.equal("crop", manipulator.resolve_fit("crop-101-102"))
            assert.equal("fit", manipulator.resolve_fit("invalid"))
            assert.equal("fit", manipulator.resolve_fit(nil))
        end)
    end)

    describe("test too large for processing", function()
        it("input", function()
            local test_image = fixtures.input_jpg_large
            local image, api_err = api:process(test_image, {})

            assert.falsy(image)
            assert.equal(404, api_err.status)
            assert.truthy(api_err.message:find("Image is too large for processing"))
        end)

        it("output", function()
            local test_image = fixtures.input_jpg
            local image, api_err = api:process(test_image, {
                w = "8500",
                h = "8500",
                t = "absolute"
            })

            assert.falsy(image)
            assert.equal(404, api_err.status)
            assert.truthy(api_err.message:find("Requested image dimensions are too large"))
        end)
    end)

    it("test without enlargement", function()
        assert.True(manipulator.without_enlargement("fit"))
        assert.True(manipulator.without_enlargement("squaredown"))
        assert.False(manipulator.without_enlargement("fitup"))
        assert.False(manipulator.without_enlargement("square"))
        assert.False(manipulator.without_enlargement("absolute"))
        assert.False(manipulator.without_enlargement("letterbox"))
        assert.False(manipulator.without_enlargement("invalid"))
        assert.False(manipulator.without_enlargement(nil))
    end)

    it("test fit", function()
        local test_image = fixtures.input_jpg
        local params = {
            w = "320",
            h = "240"
        }

        local image = api:process(test_image, params)

        assert.equal(294, image:width())
        assert.equal(240, image:height())
        assert.False(utils.has_alpha(image))
    end)

    -- Provide only one dimension, should default to fit
    describe("test fixed", function()
        local test_image = fixtures.input_jpg

        it("width", function()
            local params = {
                w = "320"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(261, image:height())
            assert.False(utils.has_alpha(image))
        end)

        it("height", function()
            local params = {
                h = "320"
            }

            local image = api:process(test_image, params)

            assert.equal(392, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)
    end)

    it("test invalid height", function()
        local test_image = fixtures.input_jpg
        local params = {
            w = "320",
            h = "-100"
        }

        local image = api:process(test_image, params)

        assert.equal(320, image:width())
        assert.equal(261, image:height())
        assert.False(utils.has_alpha(image))
    end)

    it("test identity transform", function()
        local test_image = fixtures.input_jpg
        local params = {}

        local image = api:process(test_image, params)

        assert.equal(2725, image:width())
        assert.equal(2225, image:height())
        assert.False(utils.has_alpha(image))
    end)

    it("test fitup", function()
        local test_image = fixtures.input_jpg
        local params = {
            w = "3000",
            t = "fitup"
        }

        local image = api:process(test_image, params)

        assert.equal(3000, image:width())
        assert.equal(2450, image:height())
        assert.False(utils.has_alpha(image))
    end)

    describe("test square", function()
        local test_image = fixtures.input_jpg

        it("normal", function()
            local params = {
                w = "320",
                h = "240",
                t = "square"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.False(utils.has_alpha(image))
        end)

        it("upscale", function()
            local params = {
                w = "3000",
                t = "square"
            }

            local image = api:process(test_image, params)

            assert.equal(3000, image:width())
            assert.equal(2450, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Do not enlarge when input width is already less than output width
        it("down width", function()
            local params = {
                w = "2800",
                t = "squaredown"
            }

            local image = api:process(test_image, params)

            assert.equal(2725, image:width())
            assert.equal(2225, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Do not enlarge when input height is already less than output height
        it("down height", function()
            local params = {
                h = "2300",
                t = "squaredown"
            }

            local image = api:process(test_image, params)

            assert.equal(2725, image:width())
            assert.equal(2225, image:height())
            assert.False(utils.has_alpha(image))
        end)
    end)

    describe("test tiff", function()
        local test_image = fixtures.input_tiff

        it("square", function()
            local params = {
                w = "240",
                h = "320",
                t = "square"
            }

            local image = api:process(test_image, params)

            assert.equal(240, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Width or height considering ratio (portrait)
        it("ratio portrait", function()
            local params = {
                w = "320",
                h = "320"
            }

            local image = api:process(test_image, params)

            assert.equal(243, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)
    end)

    -- Width or height considering ratio (landscape)
    it("test jpg ratio landscape", function()
        local test_image = fixtures.input_jpg
        local params = {
            w = "320",
            h = "320"
        }

        local image = api:process(test_image, params)

        assert.equal(320, image:width())
        assert.equal(261, image:height())
        assert.False(utils.has_alpha(image))
    end)

    describe("test absolute", function()
        local test_image = fixtures.input_jpg

        -- Downscale width and height, ignoring aspect ratio
        it("downscale", function()
            local params = {
                w = "320",
                h = "320",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Downscale width, ignoring aspect ratio
        it("downscale width", function()
            local params = {
                w = "320",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(2225, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Downscale height, ignoring aspect ratio
        it("downscale height", function()
            local params = {
                h = "320",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(2725, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Upscale width and height, ignoring aspect ratio
        it("upscale", function()
            local params = {
                w = "3000",
                h = "3000",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(3000, image:width())
            assert.equal(3000, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Upscale width, ignoring aspect ratio
        it("upscale width", function()
            local params = {
                w = "3000",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(3000, image:width())
            assert.equal(2225, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Upscale height, ignoring aspect ratio
        it("upscale height", function()
            local params = {
                h = "3000",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(2725, image:width())
            assert.equal(3000, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Downscale width, upscale height, ignoring aspect ratio
        it("downscale width upscale height", function()
            local params = {
                w = "320",
                h = "3000",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(3000, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Upscale width, downscale height, ignoring aspect ratio
        it("upscale width downscale height", function()
            local params = {
                w = "3000",
                h = "320",
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(3000, image:width())
            assert.equal(320, image:height())
            assert.False(utils.has_alpha(image))
        end)

        -- Upscale width, downscale height, ignoring aspect ratio
        it("identity transform", function()
            local params = {
                t = "absolute"
            }

            local image = api:process(test_image, params)

            assert.equal(2725, image:width())
            assert.equal(2225, image:height())
            assert.False(utils.has_alpha(image))
        end)
    end)

    describe("test from", function()
        -- From CMYK to sRGB
        it("CMYK to sRGB", function()
            local test_image = fixtures.input_jpg_with_cmyk_profile
            local params = {
                w = "320"
            }

            local image = api:process(test_image, params)

            assert.equal("rgb", image:interpretation())
            assert.equal(320, image:width())
        end)

        -- From profile-less CMYK to sRGB
        it("profile-less CMYK to sRGB", function()
            local test_image = fixtures.input_jpg_with_cmyk_no_profile
            local expected_image = fixtures.expected_dir .. "/colourspace.cmyk-without-profile.jpg"
            local params = {
                w = "320"
            }

            local image = api:process(test_image, params)

            assert.equal("rgb", image:interpretation())
            assert.equal(320, image:width())
            assert.similar_image(expected_image, image)
        end)
    end)
end)