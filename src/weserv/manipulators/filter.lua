local vips = vips

--- Filter manipulator
-- @module filter
local manipulator = {}

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.filt ~= nil
end

--- Perform filter image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    if args.filt == "greyscale" then
        -- Perform greyscale filter manipulation.
        image = image:colourspace("b-w")
    end

    if args.filt == "sepia" then
        -- Perform sepia filter manipulation.
        local sepia = vips.Image.new_from_array({
            { 0.3588, 0.7044, 0.1368 },
            { 0.2990, 0.5870, 0.1140 },
            { 0.2392, 0.4696, 0.0912 }
        })

        if args.has_alpha then
            -- Separate alpha channel
            local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
            local alpha = image:extract_band(image:bands() - 1, { n = 1 })
            return image_without_alpha:recomb(sepia) .. alpha
        end

        image = image:recomb(sepia)
    end

    if args.filt == "negate" then
        -- Perform negate filter manipulation.
        if args.has_alpha then
            -- Separate alpha channel
            local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
            local alpha = image:extract_band(image:bands() - 1, { n = 1 })
            return image_without_alpha:invert() .. alpha
        end

        image = image:invert()
    end

    return image
end

return manipulator