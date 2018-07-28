local vips = require "vips"
local utils = require "weserv.helpers.utils"

--- Filter manipulator
-- @module filter
local manipulator = {}

--- Perform greyscale filter manipulation.
-- @param image The source image.
-- @return The manipulated image.
function manipulator.greyscale_filter(image)
    return image:colourspace('b-w')
end

--- Perform sepia filter manipulation.
-- @param image The source image.
-- @return The manipulated image.
function manipulator.sepia_filter(image)
    local sepia = vips.Image.new_from_array({
        { 0.3588, 0.7044, 0.1368 },
        { 0.2990, 0.5870, 0.1140 },
        { 0.2392, 0.4696, 0.0912 }
    })

    if utils.has_alpha(image) then
        -- Separate alpha channel
        local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
        local alpha = image:extract_band(image:bands() - 1, { n = 1 })
        return image_without_alpha:recomb(sepia) .. alpha
    end

    return image:recomb(sepia)
end

--- Perform negate filter manipulation.
-- @param image The source image.
-- @return The manipulated image.
function manipulator.negate_filter(image)
    if utils.has_alpha(image) then
        -- Separate alpha channel
        local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
        local alpha = image:extract_band(image:bands() - 1, { n = 1 })
        return image_without_alpha:invert() .. alpha
    end

    return image:invert()
end

--- Perform filter image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
function manipulator:process(image, args)
    if args.filt == 'greyscale' then
        image = manipulator.greyscale_filter(image)
    end

    if args.filt == 'sepia' then
        image = manipulator.sepia_filter(image)
    end

    if args.filt == 'negate' then
        image = manipulator.negate_filter(image)
    end

    return self:next(image, args)
end

return manipulator