local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("mask manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.mask"
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

    it("test should process", function()
        assert.True(manipulator.should_process({
            mask = "circle"
        }))
        assert.True(manipulator.should_process({
            mask = "ellipse"
        }))
        assert.True(manipulator.should_process({
            mask = "hexagon"
        }))
        assert.True(manipulator.should_process({
            mask = "pentagon"
        }))
        assert.True(manipulator.should_process({
            mask = "pentagon-180"
        }))
        assert.True(manipulator.should_process({
            mask = "square"
        }))
        assert.True(manipulator.should_process({
            mask = "star"
        }))
        assert.True(manipulator.should_process({
            mask = "heart"
        }))
        assert.True(manipulator.should_process({
            mask = "triangle"
        }))
        assert.True(manipulator.should_process({
            mask = "triangle-180"
        }))
        assert.False(manipulator.should_process({
            mask = nil
        }))
        assert.False(manipulator.should_process({
            mask = "a"
        }))
        assert.False(manipulator.should_process({
            mask = "-1"
        }))
        assert.False(manipulator.should_process({
            mask = "100"
        }))
    end)

    describe("test mask", function()
        it("circle", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-circle.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "circle"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("circle trim", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-circle-trim.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "circle",
                mtrim = "true",
            }

            local image = api:process(test_image, params)

            assert.equal(240, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("ellipse", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-ellipse.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "ellipse"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("triangle", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-triangle.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "triangle"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("triangle tilted upside down", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-triangle-180.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "triangle-180"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("pentagon", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-pentagon.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "pentagon"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("pentagon tilted upside down", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-pentagon-180.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "pentagon-180"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("hexagon", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-hexagon.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "hexagon"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("square", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-square.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "square"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("star", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-star.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "star"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("heart", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. "/mask-heart.jpg"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "heart"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("png transparent", function()
            local test_image = fixtures.input_png_overlay_layer0
            local expected_image = fixtures.expected_dir .. "/mask-star-trans.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "star"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image, 15)
        end)

        it("png transparent background", function()
            local test_image = fixtures.input_png_overlay_layer0
            local expected_image = fixtures.expected_dir .. "/mask-star-trans-bg.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "star",
                mbg = "red"
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image, 12)
        end)

        it("png 2 channels", function()
            local test_image = fixtures.input_png_with_grey_alpha
            local expected_image = fixtures.expected_dir .. "/mask-2channel.png"
            local params = {
                w = "320",
                h = "240",
                t = "square",
                mask = "triangle-180"
            }

            local image = api:process(test_image, params)

            assert.equal(4, image:bands())
            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)