local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("sharpen manipulator", function()
    local api, manipulator

    setup(function()
        manipulator = require "weserv.manipulators.sharpen"
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

    it("test resolve sharpen", function()
        assert.are.same({ 10.0, 2.0, -1.0 }, { manipulator.resolve_sharpen('10') })
        assert.are.same({ 50.5, 2.0, -1.0 }, { manipulator.resolve_sharpen('50.5') })
        assert.are.same({ 5.0, 5.0, 3.0 }, { manipulator.resolve_sharpen('5,5,3') })
        assert.are.same({ 5.0, 5.0, 3.0 }, { manipulator.resolve_sharpen('5,5,3,4') })
        assert.are.same({ 5.0, 2.0, 3.0 }, { manipulator.resolve_sharpen('5,-1,3') })
        assert.are.same({ 1.0, 2.0, -1.0 }, { manipulator.resolve_sharpen('a') })
        assert.are.same({ 1.0, 2.0, -1.0 }, { manipulator.resolve_sharpen('-1') })
        assert.are.same({ 1.0, 2.0, -1.0 }, { manipulator.resolve_sharpen('10001') })
    end)

    describe("test sharpen", function()
        -- Specific radius 10 (sigma 6)
        it("radius 10", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/sharpen-10.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = '1,2,6'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- Specific radius 3 (sigma 1.5) and levels 0.5, 2.5
        it("radius 3", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/sharpen-3-0.5-2.5.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = '0.5,2.5,1.5'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- Specific radius 5 (sigma 3.5) and levels 2, 4
        it("radius 5", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/sharpen-5-2-4.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = '2,4,3.5'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        -- Specific radius 5 (sigma 3.5) and levels 4, 8 with alpha channel
        it("radius 5 with transparency", function()
            local test_image = fixtures.input_png_with_transparency
            local expected_image = fixtures.expected_dir .. '/sharpen-rgba.png'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = '4,8,5'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("mild", function()
            local test_image = fixtures.input_jpg
            local expected_image = fixtures.expected_dir .. '/sharpen-mild.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = 'true'
            }

            local image = api:process(test_image, params)

            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)

        it("cmyk", function()
            local test_image = fixtures.input_jpg_with_cmyk_profile
            local expected_image = fixtures.expected_dir .. '/sharpen-cmyk.jpg'
            local params = {
                w = '320',
                h = '240',
                t = 'square',
                sharp = '1,2,6'
            }

            local image = api:process(test_image, params)

            assert.equal('srgb', image:interpretation())
            assert.equal(320, image:width())
            assert.equal(240, image:height())
            assert.similar_image(expected_image, image)
        end)
    end)
end)