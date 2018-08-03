local utils = require "weserv.helpers.utils"
local math = math

--- Letterbox manipulator
-- @module letterbox
local manipulator = {}

--- Perform letterbox image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
function manipulator:process(image, args)
    local width, height = args.w, args.h
    local image_width, image_height = image:width(), image:height()

    if (image_width ~= width or image_height ~= height) and args.t == "letterbox" then
        -- Always letterbox with a transparent background;
        -- the background manipulator will handle the background color.
        local background = { 0, 0, 0, 0 }

        -- Add non-transparent alpha channel, if required
        if not utils.has_alpha(image) then
            local result = image:new_from_image(255)
            image = image .. result
        end

        local left = math.floor(((width - image_width) / 2) + 0.5)
        local top = math.floor(((height - image_height) / 2) + 0.5)

        image = image:embed(left, top, width, height, {
            extend = "background",
            background = background
        })
    end

    return self:next(image, args)
end

return manipulator