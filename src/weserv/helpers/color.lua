local type = type
local unpack = unpack
local tonumber = tonumber
local setmetatable = setmetatable
local str_rep = string.rep
local str_lower = string.lower
local str_format = string.format

-- The 140 color names supported by all modern browsers.
local colors = {
    aliceblue = "F0F8FF",
    antiquewhite = "FAEBD7",
    aqua = "00FFFF",
    aquamarine = "7FFFD4",
    azure = "F0FFFF",
    beige = "F5F5DC",
    bisque = "FFE4C4",
    black = "000000",
    blanchedalmond = "FFEBCD",
    blue = "0000FF",
    blueviolet = "8A2BE2",
    brown = "A52A2A",
    burlywood = "DEB887",
    cadetblue = "5F9EA0",
    chartreuse = "7FFF00",
    chocolate = "D2691E",
    coral = "FF7F50",
    cornflowerblue = "6495ED",
    cornsilk = "FFF8DC",
    crimson = "DC143C",
    cyan = "00FFFF",
    darkblue = "00008B",
    darkcyan = "008B8B",
    darkgoldenrod = "B8860B",
    darkgray = "A9A9A9",
    darkgreen = "006400",
    darkkhaki = "BDB76B",
    darkmagenta = "8B008B",
    darkolivegreen = "556B2F",
    darkorange = "FF8C00",
    darkorchid = "9932CC",
    darkred = "8B0000",
    darksalmon = "E9967A",
    darkseagreen = "8FBC8F",
    darkslateblue = "483D8B",
    darkslategray = "2F4F4F",
    darkturquoise = "00CED1",
    darkviolet = "9400D3",
    deeppink = "FF1493",
    deepskyblue = "00BFFF",
    dimgray = "696969",
    dodgerblue = "1E90FF",
    firebrick = "B22222",
    floralwhite = "FFFAF0",
    forestgreen = "228B22",
    fuchsia = "FF00FF",
    gainsboro = "DCDCDC",
    ghostwhite = "F8F8FF",
    gold = "FFD700",
    goldenrod = "DAA520",
    gray = "808080",
    green = "008000",
    greenyellow = "ADFF2F",
    honeydew = "F0FFF0",
    hotpink = "FF69B4",
    indianred = "CD5C5C",
    indigo = "4B0082",
    ivory = "FFFFF0",
    khaki = "F0E68C",
    lavender = "E6E6FA",
    lavenderblush = "FFF0F5",
    lawngreen = "7CFC00",
    lemonchiffon = "FFFACD",
    lightblue = "ADD8E6",
    lightcoral = "F08080",
    lightcyan = "E0FFFF",
    lightgoldenrodyellow = "FAFAD2",
    lightgray = "D3D3D3",
    lightgreen = "90EE90",
    lightpink = "FFB6C1",
    lightsalmon = "FFA07A",
    lightseagreen = "20B2AA",
    lightskyblue = "87CEFA",
    lightslategray = "778899",
    lightsteelblue = "B0C4DE",
    lightyellow = "FFFFE0",
    lime = "00FF00",
    limegreen = "32CD32",
    linen = "FAF0E6",
    magenta = "FF00FF",
    maroon = "800000",
    mediumaquamarine = "66CDAA",
    mediumblue = "0000CD",
    mediumorchid = "BA55D3",
    mediumpurple = "9370DB",
    mediumseagreen = "3CB371",
    mediumslateblue = "7B68EE",
    mediumspringgreen = "00FA9A",
    mediumturquoise = "48D1CC",
    mediumvioletred = "C71585",
    midnightblue = "191970",
    mintcream = "F5FFFA",
    mistyrose = "FFE4E1",
    moccasin = "FFE4B5",
    navajowhite = "FFDEAD",
    navy = "000080",
    oldlace = "FDF5E6",
    olive = "808000",
    olivedrab = "6B8E23",
    orange = "FFA500",
    orangered = "FF4500",
    orchid = "DA70D6",
    palegoldenrod = "EEE8AA",
    palegreen = "98FB98",
    paleturquoise = "AFEEEE",
    palevioletred = "DB7093",
    papayawhip = "FFEFD5",
    peachpuff = "FFDAB9",
    peru = "CD853F",
    pink = "FFC0CB",
    plum = "DDA0DD",
    powderblue = "B0E0E6",
    purple = "800080",
    rebeccapurple = "663399",
    red = "FF0000",
    rosybrown = "BC8F8F",
    royalblue = "4169E1",
    saddlebrown = "8B4513",
    salmon = "FA8072",
    sandybrown = "F4A460",
    seagreen = "2E8B57",
    seashell = "FFF5EE",
    sienna = "A0522D",
    silver = "C0C0C0",
    skyblue = "87CEEB",
    slateblue = "6A5ACD",
    slategray = "708090",
    snow = "FFFAFA",
    springgreen = "00FF7F",
    steelblue = "4682B4",
    tan = "D2B48C",
    teal = "008080",
    thistle = "D8BFD8",
    tomato = "FF6347",
    turquoise = "40E0D0",
    violet = "EE82EE",
    wheat = "F5DEB3",
    white = "FFFFFF",
    whitesmoke = "F5F5F5",
    yellow = "FFFF00",
    yellowgreen = "9ACD32",
}

--- Color helper module
-- @module color
local color_helper = {}
local mt = { __index = color_helper }

--- Instantiate a Color helper object.
-- @param value The color value.
function color_helper.new(value)
    if value ~= nil then
        local value_lower = str_lower(value)
        if colors[value_lower] ~= nil then
            value = colors[value_lower]
        end
    end

    local color = {}
    color.alpha, color.red, color.green, color.blue = color_helper.parse(value)

    return setmetatable(color, mt)
end

--- Try to convert a string to a decimal ARGB sequence.
-- Allowed formats:
-- [#]RGB
-- [#]ARGB
-- [#]RRGGBB
-- [#]AARRGGBB
-- @param color Hex color representation
-- @return a decimal ARGB sequence
function color_helper.parse(color)
    -- Default to transparent
    local default = { 0, 0, 0, 0 }

    -- If it's not a string; return default
    if type(color) ~= "string" then
        return unpack(default)
    end

    -- Remove any leading hash and make sure that the string is uppercased.
    color = color:gsub("^#", ""):upper()

    -- Check if it's a valid hexadecimal color
    if tonumber(color, 16) == nil then
        return unpack(default)
    end

    -- Get string length
    local color_length = #color

    -- Invalid color; return default
    if color_length < 3 or color_length == 5 or color_length > 8 then
        return unpack(default)
    end

    -- RGB -> RRGGBB
    if color_length == 3 then
        local r = color:sub(1, 1)
        local g = color:sub(2, 2)
        local b = color:sub(3, 3)

        color = r .. r .. g .. g .. b .. b
    end

    -- ARGB -> AARRGGBB
    if color_length == 4 then
        local a = color:sub(1, 1)
        local r = color:sub(2, 2)
        local g = color:sub(3, 3)
        local b = color:sub(4, 4)

        color = a .. a .. r .. r .. g .. g .. b .. b
    end

    -- Pad the string to AARRGGBB format
    color = str_rep("F", 8 - #color) .. color

    -- Color is now AARRGGBB; return the decimal ARGB sequence
    local result = {}
    for i = 1, 8, 2 do
        result[#result + 1] = tonumber(color:sub(i, i + 2 - 1), 16)
    end

    return unpack(result)
end

--- Format color to RGBA table.
-- The formatted RGBA color.
function color_helper:to_rgba()
    return { self.red, self.green, self.blue, self.alpha }
end

function color_helper:to_rgba_string()
    return "rgba(" .. self.red .. "," ..
            self.green .. "," ..
            self.blue .. "," ..
            str_format("%.2f", self.alpha * 0.00392156862) .. ")"
end

--- Indicates if this color is completely transparent.
-- @return Boolean indicating if the color is transparent.
function color_helper:is_transparent()
    return self.alpha == 0
end

--- Indicates if this color has an alpha channel.
-- @return Boolean indicating if the color has an alpha channel.
function color_helper:has_alpha_channel()
    return self.alpha < 255
end

return color_helper