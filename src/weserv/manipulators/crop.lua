local math = math
local table = table
local string = string
local unpack = unpack
local tonumber = tonumber

-- Resolve crop.
--
-- @param crop The given crop.
-- @return The resolved crop.
local function resolve_crop(crop)
    if crop == nil then
        return 50, 50
    end

    local crop_methods = {
        ['top-left'] = { 0, 0 },
        ['t'] = { 50, 0 }, -- Deprecated use top instead
        ['top'] = { 50, 0 },
        ['top-right'] = { 100, 0 },
        ['l'] = { 0, 50 }, -- Deprecated use left instead
        ['left'] = { 0, 50 },
        ['center'] = { 50, 50 },
        ['r'] = { 100, 50 }, -- Deprecated use right instead
        ['right'] = { 100, 50 },
        ['bottom-left'] = { 0, 100 },
        ['bottom'] = { 50, 100 },
        ['bottom-right'] = { 100, 100 }
    }

    if crop_methods[crop] ~= nil then
        return unpack(crop_methods[crop])
    end

    -- Focal point
    if crop:sub(1, string.len('crop-')) == 'crop-' then
        local coordinates = {}
        local count = 0
        for c in crop:sub(5):gmatch('([^-%D]+)') do
            if count >= 3 then
                return 50, 50
            end

            local coordinate = tonumber(c)
            if coordinate == nil or coordinate > 100 then
                return 50, 50
            end

            table.insert(coordinates, coordinate)
            count = count + 1
        end

        if count == 2 then
            return unpack(coordinates)
        end
    end

    -- Still here? Always center on default then.
    return 50, 50
end

-- Calculate the (left, top) coordinates of the output image
-- within the input image, applying the given x and y offsets.
--
-- @param in_coordinates The image width/height.
-- @param out_coordinates The output width/height.
-- @param offsets The x/y offset.
--
-- @return The crop offset.
local function calculate_crop(in_coordinates, out_coordinates, offsets)
    -- Default values
    local left, top = 0, 0

    -- Assign only if valid
    if offsets[1] >= 0 and offsets[1] < (in_coordinates[1] - out_coordinates[1]) then
        left = offsets[1]
    elseif offsets[1] >= (in_coordinates[1] - out_coordinates[1]) then
        left = in_coordinates[1] - out_coordinates[1]
    end

    if offsets[2] >= 0 and offsets[2] < (in_coordinates[2] - out_coordinates[2]) then
        top = offsets[2]
    elseif offsets[2] >= (in_coordinates[2] - out_coordinates[2]) then
        top = in_coordinates[2] - out_coordinates[2]
    end

    -- The resulting left and top could have been outside
    -- the image after calculation from bottom / right edges.
    if left < 0 then
        left = 0
    end

    if top < 0 then
        top = 0
    end

    return left, top
end

-- Resolve crop coordinates.
--
-- @param crop
-- @param image_width
-- @param image_height
--
-- @return The resolved coordinates.
local function resolve_crop_coordinates(crop, image_width, image_height)
    if crop == nil then
        return nil
    end

    local coordinates = {}
    local count = 0
    for c in crop:gmatch('([^,%D]+)') do
        if count >= 5 then
            return nil
        end

        local coordinate = tonumber(c)
        if coordinate == nil then
            return nil
        end

        table.insert(coordinates, coordinate)
        count = count + 1
    end

    if count ~= 4 or
            coordinates[1] <= 0 or
            coordinates[2] <= 0 or
            coordinates[3] < 0 or
            coordinates[4] < 0 or
            coordinates[3] >= image_width or
            coordinates[4] >= image_height then
        return nil
    end

    return coordinates
end

--  Limit coordinates to image boundaries.
--
-- @param image The source image.
-- @param coordinates The coordinates.
--
-- @return The limited coordinates.
local function limit_to_image_boundaries(image, coordinates)
    if coordinates[1] > (image:width() - coordinates[3]) then
        coordinates[1] = image:width() - coordinates[3]
    end

    if coordinates[2] > (image:height() - coordinates[4]) then
        coordinates[2] = image:height() - coordinates[4]
    end

    return coordinates
end

local manipulator = {}

-- Perform crop image manipulation.
function manipulator:process(image, args)
    local width, height = args.w, args.h
    local image_width, image_height = image:width(), image:height()

    local is_smart_crop = args.a ~= nil and (args.a == 'entropy' or args.a == 'attention')
    local is_crop_needed = args.t ~= nil and (args.t == 'square' or args.t == 'squaredown' or args.t:sub(1, string.len('crop')) == 'crop')

    if (image_width ~= width or image_height ~= height) and is_crop_needed then
        local min_width = math.min(image_width, width)
        local min_height = math.min(image_height, height)

        if is_smart_crop then
            -- Need to copy to memory, we have to stay seq.
            image = image:copy_memory():smartcrop(min_width, min_height, {
                interesting = args.a
            })
        else
            local offset_percentage_x, offset_percentage_y = resolve_crop(args.a)

            local offset_x = math.floor(((image_width - width) * (offset_percentage_x / 100)) + 0.5)
            local offset_y = math.floor(((image_height - height) * (offset_percentage_y / 100)) + 0.5)

            local left, top = calculate_crop({ image_width, image_height }, { width, height }, { offset_x, offset_y })

            image = image:crop(left, top, min_width, min_height)
        end

        -- Update to actual image dimensions
        image_width = min_width
        image_height = min_height
    end

    local coordinates = resolve_crop_coordinates(args.crop, image_width, image_height)

    if coordinates ~= nil then
        coordinates = limit_to_image_boundaries(image, coordinates)
        image = image:crop(coordinates[3], coordinates[4], coordinates[1], coordinates[2])
    end

    return self:next(image, args)
end

return manipulator