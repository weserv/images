local math_floor = math.floor

--- Letterbox manipulator
-- @module letterbox
local manipulator = {}

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.t == "letterbox"
end

--- Perform letterbox image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local width, height = args.w, args.h
    local image_width, image_height = image:width(), image:height()

    if image_width ~= width or image_height ~= height then
        -- Always letterbox with a transparent background;
        -- the background manipulator will handle the background color.
        local background = { 0, 0, 0, 0 }

        -- Add non-transparent alpha channel, if required
        if not args.has_alpha then
            local result = image:new_from_image(255)
            image = image .. result
        end

        local left = math_floor(((width - image_width) / 2) + 0.5)
        local top = math_floor(((height - image_height) / 2) + 0.5)

        image = image:embed(left, top, width, height, {
            extend = "background",
            background = background
        })

        -- The image has an alpha channel now
        args.has_alpha = true
    end

    return image
end

return manipulator