local vips = vips
local color = require "weserv.helpers.color"
local utils = require "weserv.helpers.utils"
local type = type
local tonumber = tonumber
local ipairs = ipairs
local math_pi = math.pi
local math_cos = math.cos
local math_sin = math.sin
local math_min = math.min
local math_huge = math.huge
local math_floor = math.floor
local str_sub = string.sub
local str_format = string.format
local tbl_concat = table.concat

--- Mask manipulator
-- @module shape
local manipulator = {}

--- Inspired by this JSFiddle: https://jsfiddle.net/tohan/8vwjn4cx/
-- modified to support SVG paths.
-- @param mid_x width / 2
-- @param mid_y height / 2
-- @param points Number of points (or number of sides for polygons)
-- @param outer_radius 'outer' radius of the polygon/star
-- @param inner_radius 'inner' radius of the polygon/star (if equal to outer_radius, a polygon is drawn)
-- @param initial_angle Initial angle (clockwise). By default, stars and polygons are 'pointing' up.
-- @return SVG path data, left edge of mask, top edge of mask, mask width, mask height
function manipulator.get_svg_mask(mid_x, mid_y, points, outer_radius, inner_radius, initial_angle)
    local path = {}
    local x_max, y_max = -math_huge, -math_huge
    local x_min, y_min = math_huge, math_huge
    local x_arr, y_arr = {}, {}
    for i = 0, points do
        local angle = i * 2 * math_pi / points - math_pi / 2 + initial_angle
        local radius = inner_radius
        if i % 2 == 0 then
            radius = outer_radius
        end

        local x = math_floor((mid_x + radius * math_cos(angle)) + 0.5)
        local y = math_floor((mid_y + radius * math_sin(angle)) + 0.5)

        x_arr[#x_arr + 1] = x
        y_arr[#y_arr + 1] = y

        local prepend = "L"
        if i == 0 then
            -- If an odd number of points, add an additional point at the top of the polygon
            -- this will shift the calculated center point of the shape so that the center point
            -- of the polygon is at x,y (otherwise the center is mis-located)
            if points % 2 == 1 then
                path[#path + 1] = "M0"
                path[#path + 1] = radius
            end
            prepend = "M"
        end

        path[#path + 1] = prepend .. x
        path[#path + 1] = y

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
    path[#path + 1] = "Z"

    return path, x_min, y_min, x_max - x_min, y_max - y_min
end

--- Formula from http://mathworld.wolfram.com/HeartCurve.html
-- @param mid_x width / 2
-- @param mid_y height / 2
-- @return SVG path, left edge of mask, top edge of mask, mask width, mask height
function manipulator.get_svg_heart(mid_x, mid_y)
    local path = {}
    local x_max, y_max = -math_huge, -math_huge
    local x_min, y_min = math_huge, math_huge
    local x_arr, y_arr = {}, {}
    for t = -math_pi, math_pi, 0.02 do
        local x_pt = 16 * (math_sin(t) ^ 3)
        local y_pt = 13 * math_cos(t) - 5 * math_cos(2 * t) - 2 * math_cos(3 * t) - math_cos(4 * t)

        local x = math_floor((mid_x + x_pt * mid_x) + 0.5)
        local y = math_floor((mid_y - y_pt * mid_y) + 0.5)

        x_arr[#x_arr + 1] = x
        y_arr[#y_arr + 1] = y

        local prepend = t == -math_pi and "" or "L"
        path[#path + 1] = prepend .. x
        path[#path + 1] = y

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
    path[#path + 1] = "Z"

    return path, x_min, y_min, x_max - x_min, y_max - y_min
end

--- Generates an circle SVG path.
-- See also: https://stackoverflow.com/a/10477334/1480019
-- @param cx the x coordinate of the center of the circle
-- @param cy the y coordinate of the center of the circle
-- @param r the radius of the circle
-- @return the circle represented as SVG path
function manipulator.get_svg_circle_path(cx, cy, r)
    return "M " .. cx - r .. ", " .. cy ..
            "a" .. r .. "," .. r .. " 0 1,0 " .. r * 2 ..
            ",0 a " .. r .. "," .. r .. " 0 1,0 -" .. r * 2 .. ",0"
end

--- Generates an ellipse SVG path.
-- @param rx the horizontal radius
-- @param cx the x coordinate of the center of the ellipse
-- @param ry the vertical radius
-- @param cy the y coordinate of the center of the ellipse
-- @return the ellipse represented as SVG path
function manipulator.get_svg_ellipse_path(rx, cx, ry, cy)
    return "M " .. cx - rx .. ", " .. cy ..
            "a" .. rx .. "," .. ry .. " 0 1,0 " .. rx * 2 ..
            ",0a" .. rx .. "," .. ry .. " 0 1,0 -" .. rx * 2 .. ",0"
end

--- Get the SVG mask by type.
-- @param width Image width
-- @param height Image height
-- @param mask The resolved mask type.
-- @return SVG path, left edge of mask, top edge of mask, mask width, mask height
function manipulator.get_svg_mask_by_type(width, height, mask)
    local min = math_min(width, height)
    local outer_radius = min / 2
    local mid_x = width / 2
    local mid_y = height / 2

    if mask == "ellipse" then
        -- Ellipse
        return manipulator.get_svg_ellipse_path(mid_x, mid_x, mid_y, mid_y), 0, 0, width, height
    end

    if mask == "circle" then
        -- Circle
        local x_min = mid_x - outer_radius
        local y_min = mid_y - outer_radius

        return manipulator.get_svg_circle_path(mid_x, mid_y, outer_radius), x_min, y_min, min, min
    end

    if mask == "heart" then
        -- Heart
        return manipulator.get_svg_heart(outer_radius, outer_radius)
    end

    -- 'inner' radius of the polygon/star
    local inner_radius = outer_radius

    -- Initial angle (clockwise). By default, stars and polygons are 'pointing' up.
    local initial_angle = 0.0

    -- Number of points (or number of sides for polygons)
    local points = 0

    if mask == "hexagon" then
        -- Hexagon
        points = 6
    elseif mask == "pentagon" then
        -- Pentagon
        points = 5
    elseif mask == "pentagon-180" then
        -- Pentagon tilted upside down
        points = 5
        initial_angle = math_pi
    elseif mask == "star" then
        -- 5 point star
        points = 5 * 2
        inner_radius = inner_radius * 0.382
    elseif mask == "square" then
        -- Square tilted 45 degrees
        points = 4
    elseif mask == "triangle" then
        -- Triangle
        points = 3
    elseif mask == "triangle-180" then
        -- Triangle upside down
        points = 3
        initial_angle = math_pi
    end

    return manipulator.get_svg_mask(mid_x, mid_y, points, outer_radius, inner_radius, initial_angle)
end

--- Get the transformed path "d" attribute
-- @param path SVG path.
-- @param transl_x transformation x
-- @param transl_y transformation y
-- @param scale scale
-- @return transformed path
function manipulator.get_transformed_path(path, transl_x, transl_y, scale)
    for k, v in ipairs(path) do
        if type(v) == "number" then
            -- y value
            path[k] = str_format("%.1f", v * scale + transl_y)
        elseif type(v) == "string" and str_sub(v, 1, 1) == "L" then
            -- x value
            local x_val = tonumber(str_sub(v, 2))
            path[k] = str_format("L%.1f", x_val * scale + transl_x)
        end
    end

    return path
end

--- Calculate the transformation, i.e. the translation and scaling, required
-- to get the mask to fill the image area.
-- @param image_width Image width
-- @param image_height Image height
-- @param mask_width Mask width
-- @param mask_height Mask height
-- @param mask_x left edge of mask
-- @param mask_y top edge of mask
-- @return transformation x, transformation y, scale
function manipulator.get_translation_and_scaling(image_width, image_height, mask_width, mask_height, mask_x, mask_y)
    -- how much bigger is the image relative to the path in each dimension?
    local scale_based_on_width = image_width / mask_width
    local scale_based_on_height = image_height / mask_height

    -- of the scaling factors determined in each dimension,
    -- use the smaller one; otherwise portions of the path
    -- is outside the viewport.
    local scale = math_min(scale_based_on_width, scale_based_on_height)

    -- calculate the bounding box parameters
    -- after the path has been scaled relative to the origin
    -- but before any subsequent translations have been applied
    local scaled_mask_x = mask_x * scale
    local scaled_mask_y = mask_y * scale
    local scaled_mask_width = mask_width * scale
    local scaled_mask_height = mask_height * scale

    -- calculate the centre points of the scaled but untranslated path
    -- as well as of the image
    local scaled_mask_centre_x = scaled_mask_x + (scaled_mask_width / 2)
    local scaled_mask_centre_y = scaled_mask_y + (scaled_mask_height / 2)
    local image_centre_x = image_width / 2
    local image_centre_y = image_height / 2

    -- calculate translation required to centre the mask
    -- on the image
    local mask_transl_x = image_centre_x - scaled_mask_centre_x
    local mask_transl_y = image_centre_y - scaled_mask_centre_y

    return mask_transl_x, mask_transl_y, scale
end

--- Cutout src over dst.
-- @param mask The mask image
-- @param dst The destination image
-- @return The resolved mask.
function manipulator.cutout(mask, dst)
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

--- Calculate the area to extract
-- @param image_width Image width.
-- @param image_height Image height.
-- @param trim_width Trim width.
-- @param trim_height Trim height.
-- @retrun left, top, trim_width, trim_height
function manipulator.resolve_mask_trim(image_width, image_height, trim_width, trim_height)
    local left = math_floor(((image_width - trim_width) / 2) + 0.5)
    local top = math_floor(((image_height - trim_height) / 2) + 0.5)
    return left, top, math_floor(trim_width + 0.5), math_floor(trim_height + 0.5)
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    local mask = args.mask or args.shape
    return mask == "circle" or
            mask == "ellipse" or
            mask == "hexagon" or
            mask == "pentagon" or
            mask == "pentagon-180" or
            mask == "square" or
            mask == "star" or
            mask == "heart" or
            mask == "triangle" or
            mask == "triangle-180"
end

--- Perform mask image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local mask_type = args.mask or args.shape
    local image_width, image_height = image:width(), image:height()
    local path, x_min, y_min, mask_width, mask_height = manipulator.get_svg_mask_by_type(image_width,
        image_height, mask_type)
    local mask_transl_x, mask_transl_y, scale = manipulator.get_translation_and_scaling(image_width,
        image_height, mask_width, mask_height, x_min, y_min)

    if mask_type ~= "circle" and mask_type ~= "ellipse" then
        -- Need to transform the path if the mask is not a circle or ellipse
        path = tbl_concat(manipulator.get_transformed_path(path, mask_transl_x, mask_transl_y, scale), " ")
    end

    local preserve_aspect_ratio = mask_type == "ellipse" and "none" or "xMidYMid meet"
    local svg_template = "<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\""
    svg_template = svg_template .. str_format(" width=\"%d\" height=\"%d\"", image_width, image_height)
    svg_template = svg_template .. str_format(" preserveAspectRatio=\"%s\">\n", preserve_aspect_ratio)
    svg_template = svg_template .. "%s\n"
    svg_template = svg_template .. "</svg>"

    local mask_background = color.new(args.mbg)
    local bg_has_alpha = mask_background:has_alpha_channel()

    -- Cutout first if the image or mask background has an alpha channel
    if args.has_alpha or bg_has_alpha then
        local svg_path = str_format("<path d=\"%s\"/>", path)
        local svg_mask = str_format(svg_template, svg_path)

        local mask = vips.Image.new_from_buffer(svg_mask, "", {
            access = "sequential",
        })

        image = manipulator.cutout(mask, image)

        -- The image has an alpha channel now
        args.has_alpha = true
    end

    -- If the mask background is not completely transparent; overlay the frame
    if not mask_background:is_transparent() then
        local svg_path = str_format("<path d=\"%s M0 0 h%d v%d h-%d Z\" fill-rule=\"evenodd\" fill=\"%s\"/>",
            path, image_width, image_height, image_width, mask_background:to_rgba_string())
        local svg_frame = str_format(svg_template, svg_path)

        local frame = vips.Image.new_from_buffer(svg_frame, "", {
            access = "sequential",
        })

        -- Alpha composite src over dst
        image = image:composite(frame, "over")
    end

    local mtrim = args.mtrim or args.strim

    -- Crop the image to the mask dimensions;
    -- if mtrim is defined and if it's not a ellipse
    if mtrim ~= nil and mask_type ~= "ellipse" then
        local left, top, trim_width, trim_height = manipulator.resolve_mask_trim(image_width,
            image_height,
            mask_width * scale,
            mask_height * scale)

        -- If the trim dimensions is less than the image dimensions
        if trim_width < image_width or trim_height < image_height then
            image = image:crop(left, top, trim_width, trim_height)
        end
    end

    return image
end

return manipulator