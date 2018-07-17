local utils = require "weserv.helpers.utils"
local tonumber = tonumber

-- Resolve gamma amount.
--
-- @param gam The given gamma.
-- @return The resolved gamma amount.
local function resolve_gamma(gam)
    local gamma = tonumber(gam)

    -- Gamma may not be nil and needs to be in the range of 1.0 - 3.0
    if gamma ~= nil and gamma >= 1.0 and gamma <= 3.0 then
        return gamma
    end

    -- Default gamma correction of 2.2 (sRGB)
    return 2.2
end

local manipulator = {}

-- Perform gamma image manipulation.
function manipulator:process(image, args)
    if args.gam == nil then
        return self:next(image, args)
    end

    local gamma = resolve_gamma(args.gam)

    -- Edit the gamma
    if utils.has_alpha(image) then
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

    return self:next(image, args)
end

return manipulator