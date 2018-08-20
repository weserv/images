local utils = require "weserv.helpers.utils"
local tonumber = tonumber
local str_format = string.format
local ngx_log = ngx.log
local ngx_ERR = ngx.ERR

--- Trim manipulator
-- @module Trim
local manipulator = {}

--- Resolve trim amount.
-- @param trim The given trim amount.
-- @return The resolved trim amount.
function manipulator.resolve_trim(trim)
    local trim_level = tonumber(trim)

    -- Trim amount may not be nil and needs to be in the range of 1 - 254
    if trim_level ~= nil and trim_level >= 0 and trim_level <= 254 then
        return trim_level
    end

    -- Default: 10
    return 10
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.trim ~= nil
end

--- Perform trim image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    -- Make sure that trimming is required.
    if image:width() < 3 or image:height() < 3 then
        args.trim = nil

        return image
    end

    local background

    -- Find the value of the pixel at (0, 0), `find_trim` search for all pixels
    -- significantly different from this.
    if args.has_alpha then
        -- If the image has alpha, we'll need to flatten before `getpoint`
        -- to get a correct background value.
        background = { image:flatten()(0, 0) }
    else
        background = { image(0, 0) }
    end

    -- Scale up 8-bit values to match 16-bit input image.
    local threshold = manipulator.resolve_trim(args.trim)

    -- Scale up 8-bit values to match 16-bit input image.
    if utils.is_16_bit(image:interpretation()) then
        threshold = threshold * 256
    end

    -- Search for the bounding box of the non-background area.
    local left, top, width, height = image:find_trim({
        threshold = threshold,
        background = background,
    })

    if width > 0 and height > 0 then
        -- And now crop the original image.
        return image:crop(left, top, width, height)
    else
        ngx_log(ngx_ERR, str_format("Unexpected error while trimming. Threshold (%d) is too high.", threshold))

        -- We could use shrink-on-load for the next thumbnail manipulator
        args.trim = nil

        return image
    end
end

return manipulator