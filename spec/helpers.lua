local vips = require "vips"
local utils = require "weserv.helpers.utils"
local assert = require "luassert.assert"
local say = require "say"
local type = type
local table = table
local string = string
local tonumber = tonumber

-- TODO: Wait for https://github.com/Olivine-Labs/luassert/issues/148
-- Meanwhile, we're applying patch #150 here.

-- Pre-load the ffi module, such that it becomes part of the environment
-- and Busted will not try to GC and reload it. The ffi is not suited
-- for that and will occasionally segfault if done so.
local ffi = require "ffi"

-- Patch ffi.cdef to only be called once with each definition, as it
-- will error on re-registering.
local old_cdef = ffi.cdef
local exists = {}
ffi.cdef = function(def)
    if exists[def] then
        return
    end
    exists[def] = true
    return old_cdef(def)
end

--- Stretch luminance to cover full dynamic range.
-- @param image The source image.
-- @return The normalized image.
local function normalize_image(image)
    -- Get original colourspace
    local type_before_normalize = image:interpretation()
    if type_before_normalize == 'rgb' then
        type_before_normalize = 'srgb'
    end

    -- Convert to LAB colourspace
    local lab = image:colourspace('lab')

    -- Extract luminance
    local luminance = lab:extract_band(0)

    -- Find luminance range
    local stats = luminance:stats()
    local min = stats(0, 0)
    local max = stats(1, 0)

    if min ~= max then
        -- Extract chroma
        local chroma = lab:extract_band(1, { n = 2 })

        -- Calculate multiplication factor and addition
        local f = 100.0 / (max - min)
        local a = -(min * f)

        -- Scale luminance, join to chroma, convert back to original colourspace
        local normalized = (luminance:linear({ f }, { a }) .. chroma):colourspace(type_before_normalize)

        -- Attach original alpha channel, if any
        if utils.has_alpha(image) then
            -- Extract original alpha channel
            local alpha = image:extract_band(image:bands() - 1)
            -- Join alpha channel to normalised image
            return normalized .. alpha
        end

        return normalized
    end

    return image
end

--- Generates a 64-bit-as-binary-string image fingerprint.
-- Based on the dHash gradient method - see:
-- http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
-- @param image
-- @return 64-bit-as-binary-string image fingerprint.
local function fingerprint(image)
    local thumbnail_options = {
        height = 8,
        size = 'force',
        auto_rotate = false,
        linear = false
    }

    local thumbnailImage = image:thumbnail_image(9, thumbnail_options):colourspace('b-w')
    local dhash_image = normalize_image(thumbnailImage:copy_memory()):extract_band(0):write_to_memory()

    local fingerprint_tbl = {}
    for y = 0, 7 do
        for x = 0, 7 do
            local left = dhash_image[(x * 8) + y]
            local right = dhash_image[(x * 8) + y + 1]

            fingerprint_tbl[#fingerprint_tbl + 1] = left > right and '1' or '0'
        end
    end

    return table.concat(fingerprint_tbl)
end

--- Calculate a perceptual hash of an image fingerprint.
-- @param hash
-- @return perceptual hash
local function dhash(hash)
    -- Binary to hexadecimal
    local dhash_tbl = {}
    for i = 1, #hash, 4 do
        local bytes = hash:sub(i, i + 3)
        local decimal = tonumber(bytes, 2)
        local hex = string.format("%x", decimal)

        dhash_tbl[#dhash_tbl + 1] = hex
    end

    return table.concat(dhash_tbl)
end

--- Calculates dHash hamming distance.
-- See http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
-- @param hash1
-- @param hash2
-- @return the number of bits different between two hash values.
local function dhash_distance(hash1, hash2)
    local dis = 0
    if #hash1 ~= #hash2 then
        return dis
    end

    for i = 1, #hash1 do
        if hash1:byte(i) ~= hash2:byte(i) then
            dis = dis + 1
        end
    end
    return dis
end

--- Verify similarity of expected vs actual images
-- @param state
-- @param arguments
-- @return true if images are similar, false otherwise
local function similar_image(_, arguments)
    local expected_image = arguments[1]
    local actual_image = arguments[2]

    -- Default threshold is 5
    local threshold = arguments[3] or 5

    if type(expected_image) == 'string' then
        expected_image = vips.Image.new_from_file(expected_image, {
            access = 'sequential'
        })
    end

    if type(actual_image) == 'string' then
        actual_image = vips.Image.new_from_file(actual_image, {
            access = 'sequential'
        })
    end

    assert(vips.Image.is_Image(expected_image),
        "Expected image must be a string value or image class. Got: " .. type(expected_image))
    assert(vips.Image.is_Image(actual_image),
        "Actual image must be a string value or image class. Got: " .. type(actual_image))
    assert(type(threshold) == "number", "Threshold must be a number value. Got: " .. type(threshold))

    local expected_fingerprint = fingerprint(expected_image)
    local actual_fingerprint = fingerprint(actual_image)

    local distance = dhash_distance(expected_fingerprint, actual_fingerprint)

    table.insert(arguments, 1, dhash(actual_fingerprint))
    table.insert(arguments, 1, dhash(expected_fingerprint))
    table.insert(arguments, 1, threshold)
    table.insert(arguments, 1, distance)

    return distance < threshold
end

say:set("assertion.similar_image.positive", [[
Actual image similarity distance (%s) is less than the threshold (%s).
Expected hash:
%s
Actual hash:
%s
]])
say:set("assertion.similar_image.negative", [[
Actual image similarity distance (%s) is not less than the threshold (%s).
Unexpected hash:
%s
Actual hash:
%s
]])
assert:register("assertion", "similar_image", similar_image,
    "assertion.similar_image.positive", "assertion.similar_image.negative")

--- Verifies the maximum color distance using the DE2000 algorithm
-- between two images of the same dimensions and number of channels.
-- @param state
-- @param arguments
-- @return true if there is no color difference, false otherwise
local function max_color_distance(_, arguments)
    local expected_image = arguments[1]
    local actual_image = arguments[2]

    -- Default distance is 1
    local accepted_distance = arguments[3] or 1

    if type(expected_image) == 'string' then
        expected_image = vips.Image.new_from_file(expected_image, {
            access = 'sequential'
        })
    end

    if type(actual_image) == 'string' then
        actual_image = vips.Image.new_from_file(actual_image, {
            access = 'sequential'
        })
    end

    assert(vips.Image.is_Image(expected_image),
        "Expected image must be a string value or image class. Got: " .. type(expected_image))
    assert(vips.Image.is_Image(actual_image),
        "Actual image must be a string value or image class. Got: " .. type(actual_image))
    assert(type(accepted_distance) == "number",
        "Accepted color distance must be a number value. Got: " .. type(accepted_distance))

    -- Ensure same number of channels
    if actual_image:bands() ~= expected_image:bands() then
        error('Mismatched bands')
    end

    -- Ensure same dimensions
    if actual_image:width() ~= expected_image:width() or
            actual_image:height() ~= expected_image:height() then
        error('Mismatched dimensions')
    end

    -- Premultiply and remove alpha
    if utils.has_alpha(actual_image) then
        actual_image = actual_image:premultiply():extract_band(0, { n = actual_image:bands() - 1 })
    end
    if utils.has_alpha(expected_image) then
        expected_image = expected_image:premultiply():extract_band(0, { n = expected_image:bands() - 1 })
    end

    -- Calculate color distance
    local color_distance = actual_image:dE00(expected_image):max()

    table.insert(arguments, 1, accepted_distance)
    table.insert(arguments, 1, color_distance)

    return color_distance < accepted_distance
end

say:set("assertion.max_color_distance.positive", [[
Actual image color distance (%s) is less than the expected maximum color distance (%s).
]])
say:set("assertion.max_color_distance.positive", [[
Actual image color distance (%s) is not less than the expected maximum color distance (%s).
]])
assert:register("assertion", "max_color_distance", max_color_distance,
    "assertion.max_color_distance.positive", "assertion.max_color_distance.negative")

--- Assertion to check whether a value lives in an table.
local function contains(container, contained)
    if container == contained then return true end
    local t1, t2 = type(container), type(contained)
    if t1 ~= t2 then return false end

    if t1 == 'table' then
        for k, v in pairs(contained) do
            if not contains(container[k], v) then return false end
        end
        return true
    end
    return false
end

local function contains_for_luassert(_, arguments)
    return contains(arguments[1], arguments[2])
end

say:set("assertion.contains.negative", [[
Expected table to contain value.
Expected to contain:
%s
]])
say:set("assertion.contains.positive", [[
Expected table to not contain value.
Expected to not contain:
%s
]])
assert:register("assertion", "contains", contains_for_luassert,
    "assertion.contains.negative",
    "assertion.contains.positive")
assert:register("matcher", "contains", function(_, arguments)
    local expected = arguments[1]
    return function(value)
        return contains(value, expected)
    end
end)
