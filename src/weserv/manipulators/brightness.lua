local tonumber = tonumber

--- Brightness manipulator
-- @module brightness
local manipulator = {}

--- Resolve brightness amount.
-- @param bri The given brightness.
-- @return The resolved brightness amount.
function manipulator.resolve_brightness(bri)
    local brightness = tonumber(bri)

    -- Brightness may not be nil and needs to be in the range of -100 - 100
    if brightness ~= nil and brightness >= -100 and brightness <= 100 then
        return brightness
    end

    return 0
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.bri ~= nil
end

--- Perform brightness image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local brightness = manipulator.resolve_brightness(args.bri)

    if brightness ~= 0 then
        -- Map brightness from -100/100 to -255/255 range
        brightness = brightness * 2.55

        -- Edit the brightness
        if args.has_alpha then
            -- Separate alpha channel
            local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
            local alpha = image:extract_band(image:bands() - 1, { n = 1 })
            image = image_without_alpha:linear({ 1, 1, 1 }, { brightness, brightness, brightness }) .. alpha
        else
            image = image:linear({ 1, 1, 1 }, { brightness, brightness, brightness })
        end

        --[[local old_interpretation = image:interpretation()
        local lch = image:colourspace("lch")

        -- Edit the brightness
        image = lch:add({brightness, 1, 1}):colourspace(old_interpretation)]]
    end

    return image
end

return manipulator