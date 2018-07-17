local vips = require "vips"
local utils = require "weserv.helpers.utils"
local tonumber = tonumber

-- Resolve blur amount.
--
-- @param blur The given blur.
-- @return The resolved blur amount.
local function resolve_blur(blur)
    local blur_amount = tonumber(blur)

    -- Contrast may not be nil and needs to be in the range of 0.3 - 1000
    if blur_amount ~= nil and blur_amount >= 0.3 and blur_amount <= 1000 then
        return blur_amount
    end

    return -1.0
end

local manipulator = {}

-- Perform blur image manipulation.
function manipulator:process(image, args)
    if args.blur == nil then
        return self:next(image, args)
    end

    if not args.is_premultiplied and utils.has_alpha(image) then
        -- Premultiply image alpha channel before blur transformation
        image = image:premultiply()
        args.is_premultiplied = true
    end

    local blur = resolve_blur(args.blur)

    if blur == -1.0 then
        -- Fast, mild blur - averages neighbouring pixels
        local matrix = vips.Image.new_from_array({
            { 1.0, 1.0, 1.0 },
            { 1.0, 1.0, 1.0 },
            { 1.0, 1.0, 1.0 }
        }, 9.0)

        image = image:conv(matrix)
    else
        if args.access_method == 'sequential' then
            image = image:linecache({
                tile_height = 10,
                access = 'sequential',
                threaded = true
            })
        end

        image = image:gaussblur(blur)
    end

    return self:next(image, args)
end

return manipulator