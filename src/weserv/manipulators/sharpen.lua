local vips = vips
local unpack = unpack
local tonumber = tonumber

--- Sharpen manipulator
-- @module sharpen
local manipulator = {}

--- Resolve sharpen amount.
-- @param sharp The given sharpen.
-- @return The resolved sharpen amount.
function manipulator.resolve_sharpen(sharp)
    -- Flat, jagged, sigma
    local sharpen = { 1.0, 2.0, -1.0 }

    -- Tables starts from one in Lua
    local count = 1
    for p in sharp:gmatch("[^,]+") do
        if count >= 4 then
            return unpack(sharpen)
        end

        local piece = tonumber(p)

        -- A single piece may not be nil and needs to be in the range of 1 - 10000
        if piece ~= nil and piece >= 1 and piece <= 10000 then
            sharpen[count] = piece
        end

        count = count + 1
    end

    return unpack(sharpen)
end

--- Sharpen flat and jagged areas. Use sigma of -1.0 for fast sharpen.
-- @param image The source image.
-- @param sigma Sharpening mask to apply in pixels, but comes at a performance cost. (Default: -1)
-- @param flat Sharpening to apply to flat areas. (Default: 1.0)
-- @param jagged Sharpening to apply to jagged areas. (Default: 2.0)
-- @param access_method libvips access method.
-- @return The manipulated image.
function manipulator.sharpen(image, sigma, flat, jagged, access_method)
    if sigma == -1.0 then
        -- Fast, mild sharpen
        local matrix = vips.Image.new_from_array({
            { -1.0, -1.0, -1.0 },
            { -1.0, 32, -1.0 },
            { -1.0, -1.0, -1.0 }
        }, 24.0)

        return image:conv(matrix)
    end

    -- Slow, accurate sharpen in LAB colour space, with control over flat vs jagged areas
    local old_interpretation = image:interpretation()

    if old_interpretation == "rgb" then
        old_interpretation = "srgb"
    end

    if access_method == "sequential" then
        image = image:linecache({
            tile_height = 10,
            access = "sequential",
            threaded = true,
        })
    end

    return image:sharpen({
        sigma = sigma,
        m1 = flat,
        m2 = jagged
    }):colourspace(old_interpretation)
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.sharp ~= nil
end

--- Perform sharpen image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local flat, jagged, sigma = manipulator.resolve_sharpen(args.sharp)

    return manipulator.sharpen(image, sigma, flat, jagged, args.access_method)
end

return manipulator