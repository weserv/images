local weserv_api = require "weserv.api"
local fixtures = require "spec.fixtures"

describe("orientation manipulator", function()
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

    -- Rotate by any 90-multiple angle
    describe("test rotate", function()
        it("by 90 multiple angle", function()
            local test_image = fixtures.input_jpg_320x240

            for _, angle in pairs({ -3690, -450, -90, 90, 450, 3690 }) do
                local image = api:process(test_image, {
                    ['or'] = tostring(angle)
                })

                assert.equal(240, image:width())
                assert.equal(320, image:height())
            end
        end)

        -- Rotate by any 180-multiple angle
        it("by 180 multiple angle", function()
            local test_image = fixtures.input_jpg_320x240

            for _, angle in pairs({ -3780, -540, 0, 180, 540, 3780 }) do
                local image = api:process(test_image, {
                    ['or'] = tostring(angle)
                })

                assert.equal(320, image:width())
                assert.equal(240, image:height())
            end
        end)
    end)

    -- EXIF Orientation, auto-rotate
    describe("test auto rotate", function()
        local function assert_auto_rotate(expected, test, orientation)
            assert.equal(320, test:width())
            if orientation == 'landscape' then
                assert.equal(240, test:height())
            else
                assert.equal(427, test:height())
            end

            -- Check if the EXIF orientation header is removed
            assert.equal(0, test:get_typeof('orientation'))
            assert.similar_image(expected, test)
        end

        it("landscape", function()
            for exif_tag = 1, 8 do
                local fixture = 'input_jpg_with_landscape_exif' .. exif_tag
                local test_image = fixtures[fixture]
                local expected_image = fixtures.expected_dir .. '/' .. 'Landscape_' .. exif_tag .. '-out.jpg'
                local image = api:process(test_image, {
                    w = '320'
                })

                assert_auto_rotate(expected_image, image, "landscape")
            end
        end)

        it("portrait", function()
            for exif_tag = 1, 8 do
                local fixture = 'input_jpg_with_portrait_exif' .. exif_tag
                local test_image = fixtures[fixture]
                local expected_image = fixtures.expected_dir .. '/' .. 'Portrait_' .. exif_tag .. '-out.jpg'
                local image = api:process(test_image, {
                    w = '320'
                })

                assert_auto_rotate(expected_image, image, "portrait")
            end
        end)
    end)
end)