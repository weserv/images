local color = require "weserv.helpers.color"
local utils = require "weserv.helpers.utils"

local manipulator = {}

-- Perform background image manipulation.
function manipulator:process(image, args)
    -- Skip this manipulator if:
    -- - There's no bg parameter.
    -- - The image doesn't have an alpha channel.
    if args.bg == nil or not utils.has_alpha(image) then
        return self:next(image, args)
    end

    local background = color.new(args.bg)

    if background:is_transparent() then
        return self:next(image, args)
    end

    local background_rgba = background:to_rgba()

    if image:bands() > 2 and background:has_alpha_channel() then
        -- If the image has more than two bands and the requested background color has an alpha channel;
        -- alpha compositing.

        -- Create a new image from a constant that matches the origin image dimensions
        local background_image = image:new_from_image(background_rgba)

        -- Ensure overlay is premultiplied sRGB
        background_image = background_image:premultiply()

        if not args.is_premultiplied then
            -- Premultiply image alpha channel before background transformation
            image = image:premultiply()
            args.is_premultiplied = true
        end

        -- Alpha composite src over dst
        -- Assumes alpha channels are already premultiplied and will be unpremultiplied after
        image = background_image:composite(image, 'over', { premultiplied = true })
    else
        -- If it's a 8bit-alpha channel image or the requested background color hasn't an alpha channel;
        -- then flatten the alpha out of an image, replacing it with a constant background color.
        local background_color = {
            background_rgba[1],
            background_rgba[2],
            background_rgba[3]
        }

        if image:bands() < 3 then
            -- Convert sRGB to greyscale
            background_color = (0.2126 * background_rgba[1]) +
                    (0.7152 * background_rgba[2]) +
                    (0.0722 * background_rgba[3])
        end

        -- Flatten on premultiplied images causes weird results
        -- so unpremultiply if we have a premultiplied image.
        if args.is_premultiplied then
            -- Unpremultiply image alpha and cast pixel values to integer
            image = image:unpremultiply():cast('uchar')
            args.is_premultiplied = false
        end

        image = image:flatten({ ['background'] = background_color })
    end

    return self:next(image, args)
end

return manipulator