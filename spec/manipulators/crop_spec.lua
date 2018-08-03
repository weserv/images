local weserv_api = require "weserv.api"
local weserv_utils = require "weserv.helpers.utils"
local fixtures = require "spec.fixtures"

describe("crop manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.crop"
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
        it("test resolve crop", function()
            assert.are.same({ 0, 0 }, { manipulator.resolve_crop("top-left") })
            assert.are.same({ 0, 100 }, { manipulator.resolve_crop("bottom-left") })
            assert.are.same({ 0, 50 }, { manipulator.resolve_crop("left") })
            assert.are.same({ 100, 0 }, { manipulator.resolve_crop("top-right") })
            assert.are.same({ 100, 100 }, { manipulator.resolve_crop("bottom-right") })
            assert.are.same({ 100, 50 }, { manipulator.resolve_crop("right") })
            assert.are.same({ 50, 0 }, { manipulator.resolve_crop("top") })
            assert.are.same({ 50, 100 }, { manipulator.resolve_crop("bottom") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("center") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("crop") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("center") })
            assert.are.same({ 25, 75 }, { manipulator.resolve_crop("crop-25-75") })
            assert.are.same({ 0, 100 }, { manipulator.resolve_crop("crop-0-100") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("crop-101-102") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("crop-0-100-10") })
            assert.are.same({ 50, 50 }, { manipulator.resolve_crop("invalid") })

            -- Deprecated parameters (use left, right, top and bottom instead)
            assert.are.same({ 0, 50 }, { manipulator.resolve_crop("l") })
            assert.are.same({ 100, 50 }, { manipulator.resolve_crop("r") })
            assert.are.same({ 50, 0 }, { manipulator.resolve_crop("t") })
            assert.are.same({ 50, 100 }, { manipulator.resolve_crop("b") })
        end)

        it("crop coordinates", function()
            assert.are.same({ 100, 100, 0, 0 }, manipulator.resolve_crop_coordinates("100,100,0,0", 100, 100))
            assert.are.same({ 101, 1, 1, 1 }, manipulator.resolve_crop_coordinates("101,1,1,1", 100, 100))
            assert.are.same({ 1, 101, 1, 1 }, manipulator.resolve_crop_coordinates("1,101,1,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates(nil, 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,1,1,", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,1,,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,,1,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates(",1,1,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("-1,1,1,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,1,101,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,1,1,101", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("1,1,1,1,1", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("a", 100, 100))
            assert.falsy(manipulator.resolve_crop_coordinates("", 100, 100))
        end)
    end)

    it("test crop positions", function()
        local crop_positions = {
            {
                -- Top left
                width = 320,
                height = 80,
                position = "top-left",
                fixture = "position-top.jpg"
            },
            {
                -- Top left
                width = 80,
                height = 320,
                position = "top-left",
                fixture = "position-left.jpg"
            },
            {
                -- Top
                width = 320,
                height = 80,
                position = "top",
                fixture = "position-top.jpg"
            },
            {
                -- Top right
                width = 320,
                height = 80,
                position = "top-right",
                fixture = "position-top.jpg"
            },
            {
                -- Top right
                width = 80,
                height = 320,
                position = "top-right",
                fixture = "position-right.jpg"
            },
            {
                -- Left
                width = 80,
                height = 320,
                position = "left",
                fixture = "position-left.jpg"
            },
            {
                -- Center
                width = 320,
                height = 80,
                position = "center",
                fixture = "position-center.jpg"
            },
            {
                -- Centre
                width = 80,
                height = 320,
                position = "center",
                fixture = "position-centre.jpg"
            },
            {
                -- Default (centre)
                width = 80,
                height = 320,
                position = nil,
                fixture = "position-centre.jpg"
            },
            {
                -- Right
                width = 80,
                height = 320,
                position = "right",
                fixture = "position-right.jpg"
            },
            {
                -- Bottom left
                width = 320,
                height = 80,
                position = "bottom-left",
                fixture = "position-bottom.jpg"
            },
            {
                -- Bottom left
                width = 80,
                height = 320,
                position = "bottom-left",
                fixture = "position-left.jpg"
            },
            {
                -- Bottom
                width = 320,
                height = 80,
                position = "bottom",
                fixture = "position-bottom.jpg"
            },
            {
                -- Bottom right
                width = 320,
                height = 80,
                position = "bottom-right",
                fixture = "position-bottom.jpg"
            },
            {
                -- Bottom right
                width = 80,
                height = 320,
                position = "bottom-right",
                fixture = "position-right.jpg"
            }
        }

        for _, crop in ipairs(crop_positions) do
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/" .. crop.fixture
            local params = {
                w = tostring(crop.width),
                h = tostring(crop.height),
                t = "square",
                a = crop.position,
            }

            local image = api:process(test_image, params)

            assert.equal(crop.width, image:width())
            assert.equal(crop.height, image:height())
            assert.similar_image(expected_image, image)
        end
    end)

    describe("test entropy crop", function()
        it("jpeg", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/crop-strategy-entropy.jpg"
            local params = {
                w = "80",
                h = "320",
                t = "square",
                a = "entropy"
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
            assert.equal(80, image:width())
            assert.equal(320, image:height())
            assert.False(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("png", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/crop-strategy.png"
            local params = {
                w = "320",
                h = "80",
                t = "square",
                a = "entropy"
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(320, image:width())
            assert.equal(80, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)
    end)

    describe("test attention crop", function()
        it("jpeg", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/crop-strategy-attention.jpg"
            local params = {
                w = "80",
                h = "320",
                t = "square",
                a = "attention"
            }

            local image = api:process(test_image, params)

            assert.equal(3, image:bands())
            assert.equal(80, image:width())
            assert.equal(320, image:height())
            assert.False(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)

        it("png", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. "/crop-strategy.png"
            local params = {
                w = "320",
                h = "80",
                t = "square",
                a = "attention"
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(320, image:width())
            assert.equal(80, image:height())
            assert.True(weserv_utils.has_alpha(image))
            assert.similar_image(expected_image, image)
        end)
    end)

    describe("test partial image extract", function()
        it("jpeg", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/extract.jpg"
            local params = {
                crop = "20,20,2,2"
            }

            local image = api:process(test_image, params)

            assert.equal(20, image:width())
            assert.equal(20, image:height())
            assert.similar_image(expected_image, image, 12)
        end)

        it("png", function()
            local test_image = fixtures.input_png
            local expected_image = fixtures.expected_dir .. "/extract.png"
            local params = {
                crop = "400,200,200,300"
            }

            local image = api:process(test_image, params)

            assert.equal(400, image:width())
            assert.equal(200, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("webp", function()
            local test_image = fixtures.input_webp
            local expected_image = fixtures.expected_dir .. "/extract.webp"
            local params = {
                crop = "125,200,100,50"
            }

            local image = api:process(test_image, params)

            assert.equal(125, image:width())
            assert.equal(200, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("tiff", function()
            local test_image = fixtures.input_tiff
            local expected_image = fixtures.expected_dir .. "/extract.tiff"
            local params = {
                crop = "341,529,34,63"
            }

            local image = api:process(test_image, params)

            assert.equal(341, image:width())
            assert.equal(529, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)

    it("test image resize and extract svg 72 dpi", function()
        local test_image = fixtures.input_svg
        local expected_image = fixtures.expected_dir .. "/svg72.png"
        local params = {
            w = "1024",
            t = "fitup",
            crop = "40,40,290,760"
        }

        local image = api:process(test_image, params)

        assert.equal(40, image:width())
        assert.equal(40, image:height())
        assert.similar_image(expected_image, image, 7)
    end)

    it("test image resize crop and extract", function()
        local test_image = fixtures.input_jpg
        local expected_image = fixtures.expected_dir .. "/resize-crop-extract.jpg"
        local params = {
            w = "500",
            h = "500",
            t = "square",
            a = "top",
            crop = "100,100,10,10"
        }

        local image = api:process(test_image, params)

        assert.equal(100, image:width())
        assert.equal(100, image:height())
        assert.similar_image(expected_image, image)
    end)

    it("test rotate and extract", function()
        local test_image = fixtures.input_png_with_grey_alpha
        local expected_image = fixtures.expected_dir .. "/rotate-extract.jpg"
        local params = {
            ["or"] = "90",
            crop = "280,380,20,10"
        }

        local image = api:process(test_image, params)

        assert.equal(280, image:width())
        assert.equal(380, image:height())
        assert.similar_image(expected_image, image)
    end)

    it("test limit to image boundaries", function()
        local test_image = fixtures.input_jpg
        local params = {
            crop = "30000,30000,2405,1985"
        }

        local image = api:process(test_image, params)

        assert.equal(320, image:width())
        assert.equal(240, image:height())
    end)

    describe("test negative", function()
        local test_image = fixtures.input_jpg

        it("width", function()
            local params = {
                w = "320",
                h = "240",
                t = "square",
                crop = "-10,10,10,10"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
        end)

        it("height", function()
            local params = {
                w = "320",
                h = "240",
                t = "square",
                crop = "10,-10,10,10"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
        end)
    end)

    it("test bad extract area", function()
        local test_image = fixtures.input_jpg
        local params = {
            w = "320",
            h = "240",
            t = "square",
            crop = "10,10,3000,10"
        }

        local image = api:process(test_image, params)

        assert.equal(320, image:width())
        assert.equal(240, image:height())
    end)
end)