local vips = vips
local utils = require "weserv.helpers.utils"
local error = error
local tonumber = tonumber
local math_floor = math.floor
local ngx_var = ngx.var
local HTTP_NOT_FOUND = ngx.HTTP_NOT_FOUND

-- Profile map to ensure that we use a device-
-- independent color space for the images we process.
local root_dir = ngx_var.weserv_root ~= nil and ngx_var.weserv_root or "."
local profile_map = {
    -- Default sRGB ICC profile from:
    -- https://packages.debian.org/sid/all/icc-profiles-free/filelist
    srgb = root_dir .. "/src/weserv/ICC/sRGB.icm",
    -- Convert to sRGB using default CMYK profile from:
    -- https://www.argyllcms.com/cmyk.icm
    cmyk = root_dir .. "/src/weserv/ICC/cmyk.icm",
}

local VIPS_MAX_COORD = 10000000
local MAX_IMAGE_SIZE = 71000000

--- Thumbnail manipulator
-- @module thumbnail
local manipulator = {}

--- Resolve device pixel ratio.
-- @param dpr The given device pixel ratio.
-- @return The resolved fit.
function manipulator.resolve_dpr(dpr)
    local pixel_ratio = tonumber(dpr)

    -- Pixel ratio may not be nil and needs to be in the range of 0 - 8
    if pixel_ratio ~= nil and pixel_ratio >= 0 and pixel_ratio <= 8 then
        return pixel_ratio
    end

    return 1.0
end

--- Resolve dimension.
-- @param dim The given dimension.
-- @return The resolved dimension.
function manipulator.resolve_dimension(dim)
    local dimension = tonumber(dim)

    -- Dimension may not be nil and not less than 0
    if dimension ~= nil and dimension > 0 then
        return dimension
    end

    return 0
end

--- Resolve fit.
-- @param t The given fit.
-- @return The resolved fit.
function manipulator.resolve_fit(t)
    if t == "fit" or
            t == "fitup" or
            t == "square" or
            t == "squaredown" or
            t == "absolute" or
            t == "letterbox" then
        return t
    end

    if t ~= nil and t:sub(1, 4) == "crop" then
        return "crop"
    end

    return "fit"
end

--- Indicating if we should not enlarge the output image.
-- @param fit The resolved fit.
-- @return bool
function manipulator.without_enlargement(fit)
    return fit == "fit" or fit == "squaredown"
end

--- Should this manipulator process the image?
-- @return Boolean indicating if we should process the image.
function manipulator.should_process(_)
    -- Always process the image via this manipulator.
    return true
end

--- Perform thumbnail image manipulation.
-- @param image The source image.
-- @param args The URL query arguments.
-- @return The manipulated image.
function manipulator.process(image, args)
    if image:width() * image:height() > MAX_IMAGE_SIZE then
        error({
            status = HTTP_NOT_FOUND,
            message = "Image is too large for processing. Width x height should be less than 71 megapixels.",
        })
    end

    -- Resolve target dimensions
    args.w = manipulator.resolve_dimension(args.w)
    args.h = manipulator.resolve_dimension(args.h)

    -- Resolve the device pixel ratio.
    local dpr = manipulator.resolve_dpr(args.dpr)

    -- Apply the device pixel ratio.
    args.w = args.w * dpr
    args.h = args.h * dpr

    local thumbnail_options = {
        auto_rotate = false,
        linear = false,
    }

    local is_cmyk = image:interpretation() == "cmyk"
    local embedded_profile = utils.has_profile(image)

    -- Ensure we're using a device-independent color space
    if embedded_profile or (not embedded_profile and is_cmyk) then
        -- Embedded profile; fallback in case the profile embedded in the image is broken.
        -- No embedded profile; import using default CMYK profile.
        if is_cmyk then
            thumbnail_options.import_profile = profile_map.cmyk
        else
            thumbnail_options.import_profile = profile_map.srgb
        end

        -- Convert to sRGB using embedded or import profile.
        thumbnail_options.export_profile = profile_map.srgb

        -- Use "perceptual" intent to better match imagemagick.
        thumbnail_options.intent = "perceptual"
    end

    local input_width = image:width()
    local input_height = image:height()

    local rotation = args.rotation
    local swap_needed = rotation == 90 or rotation == 270

    if swap_needed then
        input_width, input_height = input_height, input_width
    end

    -- Scaling calculations
    local thumbnail_width = args.w
    local thumbnail_height = args.h

    local fit = manipulator.resolve_fit(args.t)

    local check_max_image_size = true

    if args.w > 0 and args.h > 0 then -- Fixed width and height
        local x_factor = input_width / args.w
        local y_factor = input_height / args.h

        if fit == "square" or fit == "squaredown" or fit == "crop" then
            -- We aim to fill the bounding box, so we must use centre as crop mode
            thumbnail_options.crop = "centre"

            if x_factor < y_factor then
                thumbnail_height = math_floor((input_height / x_factor) + 0.5)
            else
                thumbnail_width = math_floor((input_width / y_factor) + 0.5)
            end
        elseif fit == "letterbox" or fit == "fit" or fit == "fitup" then
            if x_factor > y_factor then
                thumbnail_height = math_floor((input_height / x_factor) + 0.5)
            else
                thumbnail_width = math_floor((input_width / y_factor) + 0.5)
            end
        end
    elseif args.w > 0 then -- Fixed width
        if fit == "absolute" then
            thumbnail_height = input_height
            args.h = input_height
        else
            -- Auto height
            local y_factor = input_width / args.w
            args.h = math_floor((input_height / y_factor) + 0.5)

            -- Height is missing, replace with a huuuge value to prevent
            -- reduction or enlargement in that axis
            thumbnail_height = VIPS_MAX_COORD
        end
    elseif args.h > 0 then -- Fixed height
        if fit == "absolute" then
            thumbnail_width = input_width
            args.w = input_width
        else
            -- Auto width
            local x_factor = input_height / args.h
            args.w = math_floor((input_width / x_factor) + 0.5)

            -- Width is missing, replace with a huuuge value to prevent
            -- reduction or enlargement in that axis
            thumbnail_width = VIPS_MAX_COORD
        end
    else
        -- Identity transform
        thumbnail_width = input_width
        args.w = input_width

        thumbnail_height = input_height
        args.h = input_height

        -- No need to check max image size because
        -- the dimensions are unchanged.
        check_max_image_size = false
    end

    if swap_needed then
        -- Swap target output width and height when rotating by 90 or 270 degrees
        thumbnail_width, thumbnail_height = thumbnail_height, thumbnail_width
    end

    -- Assign settings
    thumbnail_options.height = thumbnail_height

    if fit == "absolute" then
        thumbnail_options.size = "force"
    elseif manipulator.without_enlargement(fit) then
        thumbnail_options.size = "down"

        -- No need to check max image size because
        -- the operation will only downsize.
        check_max_image_size = false
    else
        thumbnail_options.size = "both"
    end

    -- thumbnail_width and thumbnail_height aren't reliable anymore.
    if check_max_image_size and args.w * args.h > MAX_IMAGE_SIZE then
        error({
            status = HTTP_NOT_FOUND,
            message = "Requested image dimensions are too large. Width x height should be less than 71 megapixels.",
        })
    end

    -- Try to use shrink-on-load for JPEG and WebP, when not
    -- applying gamma correction or when trimming isn't required.
    --
    -- Note: After this operation the pixel interpretation is sRGB or RGB
    if args.trim ~= nil or args.gam ~= nil then
        return image:thumbnail_image(thumbnail_width, thumbnail_options)
    else
        return vips.Image.thumbnail(args.tmp_file_name .. args.string_options,
            thumbnail_width, thumbnail_options)
    end
end

return manipulator