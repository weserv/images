local vips = vips
local tonumber = tonumber

--- Blur manipulator
-- @module blur
local manipulator = {}

--- Resolve blur amount.
-- @param blur The given blur.
-- @return The resolved blur amount.
function manipulator.resolve_blur(blur)
    local blur_amount = tonumber(blur)

    -- Contrast may not be nil and needs to be in the range of 0.3 - 1000
    if blur_amount ~= nil and blur_amount >= 0.3 and blur_amount <= 1000 then
        return blur_amount
    end

    return -1.0
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.blur ~= nil
end

--- Perform blur image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    if not args.is_premultiplied and args.has_alpha then
        -- Premultiply image alpha channel before blur transformation
        image = image:premultiply()
        args.is_premultiplied = true
    end

    local blur = manipulator.resolve_blur(args.blur)

    if blur == -1.0 then
        -- Fast, mild blur - averages neighbouring pixels
        local matrix = vips.Image.new_from_array({
            { 1.0, 1.0, 1.0 },
            { 1.0, 1.0, 1.0 },
            { 1.0, 1.0, 1.0 }
        }, 9.0)

        image = image:conv(matrix)
    else
        if args.access_method == "sequential" then
            image = image:linecache({
                tile_height = 10,
                access = "sequential",
                threaded = true,
            })
        end

        image = image:gaussblur(blur)
    end

    return image
end

return manipulator