--- Orientation manipulator
-- @module orientation
local manipulator = {}

--- Should this manipulator process the image?
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(_)
    -- Always process the image via this manipulator.
    return true
end

--- Perform orientation image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    -- Rotate if required.
    if args.rotation ~= 0 then
        -- Need to copy to memory, we have to stay seq.
        image = image:copy_memory():rot("d" .. args.rotation)
    end

    -- Flip (mirror about Y axis) if required.
    if args.flip then
        image = image:flip("vertical")
    end

    -- Flop (mirror about X axis) if required.
    if args.flop then
        image = image:flip("horizontal")
    end

    -- Remove EXIF Orientation from image, if any
    if image:get_typeof("orientation") ~= 0 then
        image:remove("orientation")
    end

    return image
end

return manipulator