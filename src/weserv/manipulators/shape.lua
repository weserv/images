local vips = require "vips"
local utils = require "weserv.helpers.utils"
local math = math
local table = table
local string = string

-- Resolve shape.
--
-- @param args The arguments.
-- @return The resolved shape.
local function resolve_shape(args)
    if args.shape == 'circle' or
            args.shape == 'ellipse' or
            args.shape == 'hexagon' or
            args.shape == 'pentagon' or
            args.shape == 'pentagon-180' or
            args.shape == 'square' or
            args.shape == 'star' or
            args.shape == 'heart' or
            args.shape == 'triangle' or
            args.shape == 'triangle-180' then
        return args.shape
    end

    -- Deprecated use shape=circle instead
    if args.circle ~= nil then
        return 'circle'
    end

    return nil
end

-- Formula from http://mathworld.wolfram.com/HeartCurve.html
--
-- @param mid_x width / 2
-- @param mid_y height / 2
-- @return SVG path, left edge of mask, top edge of mask, mask width, mask height
local function get_svg_heart(mid_x, mid_y)
    local path = {}
    local x_max, y_max = -math.huge, -math.huge
    local x_min, y_min = math.huge, math.huge
    local x_arr, y_arr = {}, {}
    for t = -math.pi, math.pi, 0.02 do
        local x_pt = 16 * (math.sin(t) ^ 3)
        local y_pt = 13 * math.cos(t) - 5 * math.cos(2 * t) - 2 * math.cos(3 * t) - math.cos(4 * t)

        local x = math.floor((mid_x + x_pt * mid_x) + 0.5)
        local y = math.floor((mid_y - y_pt * mid_y) + 0.5)

        table.insert(x_arr, x)
        table.insert(y_arr, y)
        table.insert(path, string.format("%d %d L", x, y))

        if x > x_max then
            x_max = x
        end
        if y > y_max then
            y_max = y
        end

        if x < x_min then
            x_min = x
        end
        if y < y_min then
            y_min = y
        end
    end

    return "<path d='" .. table.concat(path) .. " Z'/>", x_min, y_min, x_max - x_min, y_max - y_min
end

-- Inspired by this JSFiddle: https://jsfiddle.net/tohan/8vwjn4cx/
-- modified to support SVG paths.
--
-- @param mid_x width / 2
-- @param mid_y height / 2
-- @param points Number of points (or number of sides for polygons)
-- @param outer_radius 'outer' radius of the polygon/star
-- @param inner_radius 'inner' radius of the polygon/star (if equal to outer_radius, a polygon is drawn)
-- @param initial_angle Initial angle (clockwise). By default, stars and polygons are 'pointing' up.
-- @return SVG path, left edge of mask, top edge of mask, mask width, mask height
local function get_svg_mask(mid_x, mid_y, points, outer_radius, inner_radius, initial_angle)
    local path = { 'M' }
    local x_max, y_max = -math.huge, -math.huge
    local x_min, y_min = math.huge, math.huge
    local x_arr, y_arr = {}, {}
    for i = 0, points do
        local angle = i * 2 * math.pi / points - math.pi / 2 + initial_angle
        local radius = inner_radius
        if i % 2 == 0 then
            radius = outer_radius
        end

        if i == 0 then
            -- If an odd number of points, add an additional point at the top of the polygon
            -- -- this will shift the calculated center point of the shape so that the center point
            -- of the polygon is at x,y (otherwise the center is mis-located)
            if points % 2 == 1 then
                table.insert(path, string.format("0 %f M", radius))
            end
        else
            table.insert(path, ' L')
        end

        local x = math.floor((mid_x + radius * math.cos(angle)) + 0.5)
        local y = math.floor((mid_y + radius * math.sin(angle)) + 0.5)

        table.insert(x_arr, x)
        table.insert(y_arr, y)
        table.insert(path, string.format("%d %d", x, y))

        if x > x_max then
            x_max = x
        end
        if y > y_max then
            y_max = y
        end

        if x < x_min then
            x_min = x
        end
        if y < y_min then
            y_min = y
        end
    end

    return "<path d='" .. table.concat(path) .. " Z'/>", x_min, y_min, x_max - x_min, y_max - y_min
end

-- Get the SVG shape.
--
-- @param width Image width
-- @param height Image height
-- @param shape The resolved shape.
-- @return SVG path, left edge of mask, top edge of mask, mask width, mask height
local function get_svg_shape(width, height, shape)
    local min = math.min(width, height)
    local outer_radius = min / 2
    local mid_x = width / 2
    local mid_y = height / 2

    if shape == 'ellipse' then
        -- Ellipse
        return string.format("<ellipse cx='%f' cy='%f' rx='%f' ry='%f'/>", mid_x, mid_y, mid_x, mid_y), 0, 0, width, height
    end

    if shape == 'circle' then
        -- Circle
        local x_min = mid_x - outer_radius
        local y_min = mid_y - outer_radius

        return string.format("<circle r='%f' cx='%f' cy='%f'/>", outer_radius, mid_x, mid_y), x_min, y_min, min, min
    end

    if shape == 'heart' then
        -- Heart
        return get_svg_heart(outer_radius, outer_radius)
    end

    -- 'inner' radius of the polygon/star
    local inner_radius = outer_radius

    -- Initial angle (clockwise). By default, stars and polygons are 'pointing' up.
    local initial_angle = 0.0

    -- Number of points (or number of sides for polygons)
    local points = 0

    if shape == 'hexagon' then
        -- Hexagon
        points = 6
    elseif shape == 'pentagon' then
        -- Pentagon
        points = 5
    elseif shape == 'pentagon-180' then
        -- Pentagon tilted upside down
        points = 5
        initial_angle = math.pi
    elseif shape == 'star' then
        -- 5 point star
        points = 5 * 2
        inner_radius = inner_radius * 0.382
    elseif shape == 'square' then
        -- Square tilted 45 degrees
        points = 4
    elseif shape == 'triangle' then
        -- Triangle
        points = 3
    elseif shape == 'triangle-180' then
        -- Triangle upside down
        points = 3
        initial_angle = math.pi
    end

    return get_svg_mask(mid_x, mid_y, points, outer_radius, inner_radius, initial_angle)
end

-- Cutout src over dst.
--
-- @param args The arguments.
-- @return The resolved shape.
local function cutout(mask, dst)
    local mask_has_alpha = utils.has_alpha(mask)
    local dst_has_alpha = utils.has_alpha(dst)

    -- we use the mask alpha if it has alpha
    if mask_has_alpha then
        mask = mask:extract_band(mask:bands() - 1, { n = 1 })
    end

    -- split dst into an optional alpha
    local dst_alpha = dst:extract_band(dst:bands() - 1, { n = 1 })

    -- we use the dst non-alpha
    if dst_has_alpha then
        dst = dst:extract_band(0, { n = dst:bands() - 1 })
    end

    -- the range of the mask and the image need to match .. one could be
    -- 16-bit, one 8-bit
    local dst_max = utils.maximum_image_alpha(dst:interpretation())
    local mask_max = utils.maximum_image_alpha(mask:interpretation())

    if dst_has_alpha then
        -- combine the new mask and the existing alpha ... there are
        -- many ways of doing this, mult is the simplest
        mask = dst_max * ((mask / mask_max) * (dst_alpha / dst_max))

        -- Not needed; after the thumbnail manipulator it's not an
        -- 16-bit image anymore.
        --[[elseif dst_max ~= mask_max then
            -- adjust the range of the mask to match the image
            mask = dst_max * (mask / mask_max)]]
    end

    -- append the mask to the image data ... the mask might be float now,
    -- we must cast the format down to match the image data
    return dst .. mask:cast(dst:format())
end

-- Calculate the area to extract
--
-- @param image_width Image width.
-- @param image_height Image height.
-- @param mask_width Mask width.
-- @param mask_height Mask height.
-- @retrun left, top, trim_width, trim_height
local function resolve_shape_trim(image_width, image_height, mask_width, mask_height)
    local x_scale = image_width / mask_width
    local y_scale = image_height / mask_height
    local scale = math.min(x_scale, y_scale)
    local trim_width = mask_width * scale
    local trim_height = mask_height * scale
    local left = math.floor(((image_width - trim_width) / 2) + 0.5)
    local top = math.floor(((image_height - trim_height) / 2) + 0.5)
    return left, top, math.floor(trim_width + 0.5), math.floor(trim_height + 0.5)
end

local manipulator = {}

-- Perform shape image manipulation.
function manipulator:process(image, args)
    local shape = resolve_shape(args)
    if shape == nil then
        return self:next(image, args)
    end

    local image_width, image_height = image:width(), image:height()

    local path, x_min, y_min, mask_width, mask_height = get_svg_shape(image_width, image_height, shape)

    local preserveAspectRatio = shape == 'ellipse' and 'none' or 'xMidYMid meet'
    local svg = '<?xml version=\'1.0\' encoding=\'UTF-8\' standalone=\'no\'?>'
    svg = svg .. string.format("<svg xmlns='http://www.w3.org/2000/svg' version='1.1' width='%d' height='%d'", image_width, image_height)
    svg = svg .. string.format(" viewBox='%f %f %f %f'", x_min, y_min, mask_width, mask_height)
    svg = svg .. string.format(" shape-rendering='geometricPrecision' preserveAspectRatio='%s'>", preserveAspectRatio)
    svg = svg .. path
    svg = svg .. '</svg>'

    local mask = vips.Image.new_from_buffer(svg, '', {
        access = 'sequential'
    })

    image = cutout(mask, image)

    -- Crop the image to the mask dimensions;
    -- if strim is defined and if it's not a ellipse
    if args.strim ~= nil and shape ~= 'ellipse' then
        local left, top, trim_width, trim_height = resolve_shape_trim(image_width, image_height, mask_width, mask_height)

        -- If the trim dimensions is less than the image dimensions
        if trim_width < image_width or trim_height < image_height then
            image = image:crop(left, top, trim_width, trim_height)
        end
    end

    return self:next(image, args)
end

return manipulator