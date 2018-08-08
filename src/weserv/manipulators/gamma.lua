local tonumber = tonumber

--- Gamma manipulator
-- @module gamma
local manipulator = {}

--- Resolve gamma amount.
-- @param gam The given gamma.
-- @return The resolved gamma amount.
function manipulator.resolve_gamma(gam)
    local gamma = tonumber(gam)

    -- Gamma may not be nil and needs to be in the range of 1.0 - 3.0
    if gamma ~= nil and gamma >= 1.0 and gamma <= 3.0 then
        return gamma
    end

    -- Default gamma correction of 2.2 (sRGB)
    return 2.2
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.gam ~= nil
end

--- Perform gamma image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local gamma = manipulator.resolve_gamma(args.gam)

    -- Edit the gamma
    if args.has_alpha then
        if not args.is_premultiplied then
            -- Premultiply image alpha channel before gamma transformation
            image = image:premultiply()
            args.is_premultiplied = true
        end

        -- Separate alpha channel
        local image_without_alpha = image:extract_band(0, { n = image:bands() - 1 })
        local alpha = image:extract_band(image:bands() - 1, { n = 1 })
        image = image_without_alpha:gamma({ exponent = 1.0 / gamma }) .. alpha
    else
        image = image:gamma({ exponent = 1.0 / gamma })
    end

    return image
end

return manipulator