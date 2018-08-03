local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("background manipulator", function()
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

    describe("test flatten", function()
        it("to black", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/flatten-white.png"
            local params = {
                w = "400",
                h = "300",
                t = "square",
                bg = "white"
            }

            local image = api:process(test_image, params)

            assert.equal(400, image:width())
            assert.equal(300, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("to orange", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/flatten-orange.png"
            local params = {
                w = "400",
                h = "300",
                t = "square",
                bg = "darkorange"
            }

            local image = api:process(test_image, params)

            assert.equal(400, image:width())
            assert.equal(300, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("to hex", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/flatten-orange.png"
            local params = {
                w = "400",
                h = "300",
                t = "square",
                bg = "FF8C00"
            }

            local image = api:process(test_image, params)

            assert.equal(400, image:width())
            assert.equal(300, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("16bit with transparency to orange", function()
            local test_image = fixtures.input_png_with_transparency_16bit
            local expected_image = fixtures.expected_dir .. "/flatten-rgb16-orange.png"
            local params = {
                bg = "darkorange"
            }

            local image = api:process(test_image, params)

            assert.equal(32, image:width())
            assert.equal(32, image:height())
            assert.max_color_distance(expected_image, image)
        end)

        it("greyscale to orange", function()
            local test_image = fixtures.input_png_with_grey_alpha
            local expected_image = fixtures.expected_dir .. "/flatten-2channel.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                filt = "greyscale",
                bg = "darkorange"
            }

            local image = api:process(test_image, params)

            assert.equal(1, image:bands())
            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("blur to orange should unpremultiply", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/flatten-blur-orange.png"
            local params = {
                w = "400",
                h = "300",
                t = "square",
                blur = "1",
                bg = "darkorange"
            }

            local image = api:process(test_image, params)

            assert.equal(400, image:width())
            assert.equal(300, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)

    it("test composite to 50% orange", function()
        local test_image = fixtures.input_png_with_transparency
        local expected_image = fixtures.expected_dir .. "/composite-50-orange.png"
        local params = {
            w = "400",
            h = "300",
            t = "square",
            bg = "80FF8C00"
        }

        local image = api:process(test_image, params)

        assert.equal(400, image:width())
        assert.equal(300, image:height())
        assert.similar_image(expected_image, image)
    end)

    describe("test ignore", function()
        it("for jpeg", function()
            local test_image = fixtures.input_jpg
            local params = {
                bg = "FF0000"
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
        end)

        it("for transparent background", function()
            local test_image = fixtures.input_png_with_transparency
            local params = {
                bg = "0FFF"
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
        end)
    end)
end)