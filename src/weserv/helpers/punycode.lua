local bit = require "bit"
local band = bit.band
local lshift = bit.lshift
local ipairs = ipairs
local select = select
local str_byte = string.byte
local str_char = string.char
local math_floor = math.floor
local tbl_concat = table.concat
local ngx_re_sub = ngx.re.sub

-- Parameter values for Punycode: https://tools.ietf.org/html/rfc3492#section-5
local base = 36
local t_min = 1
local t_max = 26
local skew = 38
local damp = 700
local initial_bias = 72
local initial_n = 128 -- 0x80
local delimiter = "-" -- 0x2D

-- Highest positive signed 32-bit float value
local max_int = 2147483647 -- aka. 0x7FFFFFFF or 2^31-1

--- Punycode module
-- @module punycode
local punycode = {}

--- Returns the code of a UTF-8 character.
-- From sarn_utf8_code_func: http://lua-users.org/wiki/ValidateUnicodeString
function punycode.utf8_code(code, ...)
    local offset = { 0, 0x3000, 0xE0000, 0x3C00000 }

    local num_bytes = select("#", ...)
    for i = 1, num_bytes do
        local b = select(i, ...)
        code = lshift(code, 6) + band(b, 63)
    end

    return code - offset[num_bytes + 1]
end

--- Convert string to universal character set coded
-- in 4 octets [0,0x7FFFFFFF]
function punycode.to_ucs4(str)
    local out = {}
    -- https://stackoverflow.com/a/22954220/1480019
    for c in str:gmatch("[%z\1-\127\194-\244][\128-\191]*") do
        out[#out + 1] = punycode.utf8_code(str_byte(c, 1, -1))
    end

    return out
end

--- This function converts a digit/integer into a basic code point.
-- whose value (when used for representing integers) is `d`,
-- which needs to be in the range `0` to `base - 1`.
-- @param digit The numeric value of a basic code point.
-- @return The basic code point.
function punycode.encode_digit(d)
    -- 0..25 map to ASCII a..z or A..Z
    -- 26..35 map to ASCII 0..9
    return d + 22 + 75 * (d < 26 and 1 or 0)
end

--- Bias adaptation function as per section 3.4 of RFC 3492.
-- https://tools.ietf.org/html/rfc3492#section-3.4
function punycode.adapt(delta, numPoints, firstTime)
    delta = firstTime and math_floor(delta / damp) or math_floor(delta / 2)

    delta = delta + math_floor(delta / numPoints)

    local k = 0

    while delta > math_floor((base - t_min) * t_max / 2) do
        delta = math_floor(delta / (base - t_min))
        k = k + 1
    end

    return base * k + math_floor((base - t_min + 1) * delta / (delta + skew))
end

--- Encoding procedure as per section 6.3 of RFC 3492.
-- https://tools.ietf.org/html/rfc3492#section-6.3
-- @param input list-table of Unicode code points.
-- @return The new encoded string.
function punycode.encode(input)
    input = punycode.to_ucs4(input)

    local codepoints = {}

    -- Cache the length.
    local input_length = #input

    -- Initialize the state.
    local n = initial_n
    local delta = 0
    local bias = initial_bias

    -- Handle the basic code points.
    for j = 1, input_length do
        local c = input[j]
        if c < 0x80 then
            codepoints[#codepoints + 1] = str_char(c)
        end
    end

    -- The number of basic code points.
    local basic_length = #codepoints

    -- The number of code points that have been handled
    local h = basic_length

    -- Finish the basic string with a delimiter unless it's empty.
    if basic_length > 0 then codepoints[#codepoints + 1] = delimiter end

    -- Main encoding loop
    while h < input_length do
        -- All non-basic code points < n have been handled already. Find
        -- the next larger one:
        local m = max_int
        for _, v in ipairs(input) do
            if v >= n and v < m then
                m = v
            end
        end

        -- Increase `delta` enough to advance the decoder's <n,i> state to <m,0>
        delta = delta + (m - n) * (h + 1)
        n = m

        for _, curr_v in ipairs(input) do
            if curr_v < n then
                delta = delta + 1
            end

            if curr_v == n then
                -- Represent delta as a generalized variable-length integer.
                local q = delta
                local k = base

                while true do
                    local t = (k <= bias) and t_min or
                            (k >= bias + t_max) and t_max or (k - bias)
                    if q < t then break end

                    local q_minus_t = q - t
                    local base_minus_t = base - t
                    codepoints[#codepoints + 1] = str_char(punycode.encode_digit(t + q_minus_t % base_minus_t))

                    q = math_floor(q_minus_t / base_minus_t)

                    k = k + base
                end

                codepoints[#codepoints + 1] = str_char(punycode.encode_digit(q))
                bias = punycode.adapt(delta, h + 1, h == basic_length)

                delta = 0
                h = h + 1
            end
        end

        delta = delta + 1
        n = n + 1
    end

    return tbl_concat(codepoints, "")
end

--- Encode a IDN domain.
-- If the domain is already ASCII, it is returned in its original state.
-- If any encoding was required, the "xn--" prefix is added.
function punycode.domain_encode(domain)
    -- Normalize RFC 3490 separators
    domain = ngx_re_sub(domain, [[[\x{3002}\x{FF0E}\x{FF61}]+]], ".", "u")

    local labels = {}

    for label in domain:gmatch("[^.]+") do
        -- Domain names can only consist of ASCII characters and aren't allowed
        -- to start or end with a hyphen
        local first, last = label:sub(1, 1), label:sub(-1)
        if first == "-" or last == "-" then
            return nil, "Invalid domain label"
        end

        -- Matches non-ASCII chars
        if label:match("[^%z\1-\127]") then
            labels[#labels + 1] = "xn--" .. punycode.encode(label)
        else
            labels[#labels + 1] = label
        end
    end

    return tbl_concat(labels, ".")
end

return punycode