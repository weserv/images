local utils = require "weserv.helpers.utils"
local ngx = ngx
local string = string
local tonumber = tonumber

-- Resolve trim amount.
--
-- @param trim The given trim amount.
-- @return The resolved trim amount.
local function resolve_trim(trim)
    local trim_level = tonumber(trim)

    -- Trim amount may not be nil and needs to be in the range of 1 - 254
    if trim_level ~= nil and trim_level >= 0 and trim_level <= 254 then
        return trim_level
    end

    -- Default: 10
    return 10
end

local manipulator = {}

-- Perform trim image manipulation.
function manipulator:process(image, args)
    -- Make sure that trimming is required.
    if not args.trim or image:width() < 3 or image:height() < 3 then
        return self:next(image, args)
    end

    local r, g, b

    -- Find the value of the pixel at (0, 0), `find_trim` search for all pixels
    -- significantly different from this.
    if utils.has_alpha(image) then
        -- If the image has alpha, we'll need to flatten before `getpoint`
        -- to get a correct background value.
        r, g, b = image:flatten()(0, 0)
    else
        r, g, b = image(0, 0)
    end

    -- Scale up 8-bit values to match 16-bit input image.
    local threshold = resolve_trim(args.trim)

    -- Scale up 8-bit values to match 16-bit input image.
    if utils.is_16_bit(image:interpretation()) then
        threshold = threshold * 256
    end

    -- Search for the bounding box of the non-background area.
    local left, top, width, height = image:find_trim({
        ['threshold'] = threshold,
        ['background'] = { r, g, b }
    })

    if width == 0 or height == 0 then
        ngx.log(ngx.ERR, string.format("Unexpected error while trimming. Threshold (%d) is too high.", threshold))

        return self:next(image, args)
    end

    -- And now crop the original image.
    image = image:crop(left, top, width, height)

    return self:next(image, args)
end

return manipulator