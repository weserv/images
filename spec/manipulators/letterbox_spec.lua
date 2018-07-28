local weserv_api = require "weserv.api"
local weserv_utils = require "weserv.helpers.utils"
local fixtures = require "spec.fixtures"


describe("letterbox manipulator", function()
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

    describe("test letterbox", function()
        -- TIFF letterbox known to cause rounding errors
        it("tiff", function()
            local test_image = fixtures.input_tiff
            local params = {
                w = '240',
                h = '320',
                t = 'letterbox',
                bg = 'white'
            }

            local image = api:process(test_image, params)

            assert.equal(240, image:width())
            assert.equal(320, image:height())
            assert.False(weserv_utils.has_alpha(image))
        end)

        -- Letterbox TIFF in LAB colourspace onto RGBA background
        it("tiff on rgba", function()
            local test_image = fixtures.input_tiff_cielab
            local expected_image = fixtures.expected_dir .. '/letterbox-lab-into-rgba.png'
            local params = {
                w = '64',
                h = '128',
                t = 'letterbox',
                bg = '80FF6600',
                output = 'png'
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(64, image:width())
            assert.equal(128, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- From CMYK to sRGB with white background, not yellow
        it("jpg cmyk to srgb with background", function()
            local test_image = fixtures.input_jpg_with_cmyk_profile
            local expected_image = fixtures.expected_dir .. '/colourspace.cmyk.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'letterbox',
                bg = 'white'
            }

            local image = api:process(test_image, params)

            assert.equal('rgb', image:interpretation())
            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. '/letterbox-4-into-4.png'
            local params = {
                w = '50',
                h = '50',
                t = 'letterbox',
                bg = 'white'
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
            assert.equal(50, image:width())
            assert.equal(50, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- 16-bit PNG with alpha channel
        it("png 16bit with transparency", function()
            local test_image = fixtures.input_png_with_transparency_16bit
            local expected_image = fixtures.expected_dir .. '/letterbox-16bit.png'
            local params = {
                w = '32',
                h = '16',
                t = 'letterbox',
                bg = 'white'
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
            assert.equal(32, image:width())
            assert.equal(16, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- 16-bit PNG with alpha channel onto RGBA
        it("png 16bit with transparency on rgba", function()
            local test_image = fixtures.input_png_with_transparency_16bit
            local expected_image = fixtures.expected_dir .. '/letterbox-16bit-rgba.png'
            local params = {
                w = '32',
                h = '16',
                t = 'letterbox'
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(32, image:width())
            assert.equal(16, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- PNG with 2 channels
        it("png 2 channels", function()
            local test_image = fixtures.input_png_with_grey_alpha
            local expected_image = fixtures.expected_dir .. '/letterbox-2channel.png'
            local params = {
                w = '32',
                h = '16',
                t = 'letterbox'
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(32, image:width())
            assert.equal(16, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- Enlarge and letterbox
        it("enlarge", function()
            local test_image = fixtures.input_png_with_one_color
            local expected_image = fixtures.expected_dir .. '/letterbox-enlarge.png'
            local params = {
                w = '320',
                h = '240',
                t = 'letterbox',
                bg = 'black'
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)