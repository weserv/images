local vips = vips
local math_abs = math.abs
local math_exp = math.exp
local tonumber = tonumber

--- Contrast manipulator
-- @module contrast
local manipulator = {}

--- Resolve contrast amount.
-- @param con The given contrast.
-- @return The resolved contrast amount.
function manipulator.resolve_contrast(con)
    local contrast = tonumber(con)

    -- Contrast may not be nil and needs to be in the range of -100 - 100
    if contrast ~= nil and contrast >= -100 and contrast <= 100 then
        return contrast
    end

    return 0
end

--- *magick's sigmoidal non-linearity contrast control
-- equivalent in libvips
--
-- This is a standard contrast adjustment technique: grey values are put through
-- an S-shaped curve which boosts the slope in the mid-tones and drops it for
-- white and black.
--
-- This will apply to RGB. And takes no account of image gamma, and applies the
-- contrast boost to R, G and B bands, thereby also boosting colourfulness.
-- @param image The source image.
-- @param contrast Strength of the contrast (typically 3-20).
function manipulator.sigmoid(image, contrast)
    -- If true increase the contrast, if false decrease the contrast.
    local sharpen = contrast > 0

    -- Midpoint of the contrast (typically 0.5).
    local midpoint = 0.5
    local contrast_abs = math_abs(contrast)

    local ushort = image:format() == "ushort"

    -- Make a identity LUT, that is, a lut where each pixel has the value of
    -- its index ... if you map an image through the identity, you get the
    -- same image back again.
    --
    -- LUTs in libvips are just images with either the width or height set
    -- to 1, and the 'interpretation' tag set to HISTOGRAM.
    --
    -- If 'ushort' is TRUE, we make a 16-bit LUT, ie. 0 - 65535 values;
    -- otherwise it's 8-bit (0 - 255)
    local lut = vips.Image.identity({ ushort = ushort })

    local range = lut:max()
    lut = lut / range

    local result

    -- The sigmoidal equation, see
    --
    -- https://www.imagemagick.org/Usage/color_mods/#sigmoidal
    --
    -- and
    --
    -- http://osdir.com/ml/video.image-magick.devel/2005-04/msg00006.html
    --
    -- Though that's missing a term -- it should be
    --
    -- (1/(1+exp(β*(α-u))) - 1/(1+exp(β*α))) /
    --   (1/(1+exp(β*(α-1))) - 1/(1+exp(β*α)))
    --
    -- ie. there should be an extra α in the second term
    if sharpen then
        local x = (1 + (((lut * -1) + midpoint) * contrast_abs):exp()) ^ -1
        local min = x:min()
        local max = x:max()

        result = (x - min) / (max - min)

    else
        local min = 1 / (1 + math_exp(contrast_abs * midpoint))
        local max = 1 / (1 + math_exp(contrast_abs * (midpoint - 1)))
        local x = lut * (max - min) + min

        result = midpoint + (((((x * -1) + 1) / x):log() / contrast_abs) * -1)
    end

    -- Rescale back to 0 - 255 or 0 - 65535
    result = result * range

    -- And get the format right ... $result will be a float image after all
    -- that maths, but we want uchar or ushort
    result = result:cast(ushort and "ushort" or "uchar")

    return image:maplut(result)
end

--- Should this manipulator process the image?
-- @param args The URL query arguments.
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(args)
    return args.con ~= nil
end

--- Perform contrast image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    local contrast = manipulator.resolve_contrast(args.con)

    if contrast ~= 0 then
        -- Map contrast from -100/100 to -30/30 range
        contrast = contrast * 0.3

        return manipulator.sigmoid(image, contrast)
    end
end

return manipulator