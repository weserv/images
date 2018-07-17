local utils = require "weserv.helpers.utils"
local ngx = ngx
local math = math
local string = string
local tonumber = tonumber

local server = {}
server.__index = server

-- Is the extension allowed to pass on to the selected save operation?
--
-- @param extension The extension.
-- @return Boolean indicating the extension is allowed.
function server.is_extension_allowed(extension)
    return extension == 'jpg' or
            extension == 'tiff' or
            extension == 'gif' or
            extension == 'png' or
            extension == 'webp'
end

-- Resolve the quality for the provided extension.
--
-- For a PNG image it returns the zlib compression level.
--
-- @param params Parameters array.
-- @param extension Image extension.
-- @return The resolved quality.
function server.resolve_quality(params, extension)
    local quality = 0

    if extension == 'jpg' or extension == 'webp' or extension == 'tiff' then
        quality = 85

        local given_quality = tonumber(params.q)

        -- Quality may not be nil and needs to be in the range of 1 - 100
        if given_quality ~= nil and given_quality >= 1 and given_quality <= 100 then
            quality = math.floor(given_quality + 0.5)
        end
    end

    if extension == 'png' then
        quality = 6

        local given_level = tonumber(params.level)

        -- zlib compression level may not be nil and needs to be in the range of 0 - 9
        if given_level ~= nil and given_level >= 0 and given_level <= 9 then
            quality = math.floor(given_level + 0.5)
        end
    end

    return quality
end

-- Get the options for a specified extension to pass on to
-- the selected save operation.
--
-- @param params Parameters array.
-- @param extension Image extension.
-- @return Any options to pass on to the selected save operation.
function server.get_buffer_options(params, extension)
    local buffer_options = {}

    if extension == 'jpg' then
        -- Strip all metadata (EXIF, XMP, IPTC)
        buffer_options['strip'] = true
        -- Set quality (default is 85)
        buffer_options['Q'] = server.resolve_quality(params, extension)
        -- Use progressive (interlace) scan, if necessary
        buffer_options['interlace'] = params.il ~= nil
        -- Enable libjpeg's Huffman table optimiser
        buffer_options['optimize_coding'] = true
    end

    if extension == 'png' then
        -- Use progressive (interlace) scan, if necessary
        buffer_options['interlace'] = params.il ~= nil
        -- zlib compression level (default is 6)
        buffer_options['compression'] = server.resolve_quality(params, extension)
        -- Use adaptive row filtering (default is none)
        if params.filter ~= nil then
            -- VIPS_FOREIGN_PNG_FILTER_ALL
            buffer_options['filter'] = 0xF8
        else
            -- VIPS_FOREIGN_PNG_FILTER_NONE
            buffer_options['filter'] = 0x08
        end
    end

    if extension == 'webp' then
        -- Strip all metadata (EXIF, XMP, IPTC)
        buffer_options['strip'] = true
        -- Set quality (default is 85)
        buffer_options['Q'] = server.resolve_quality(params, extension)
        -- Set quality of alpha layer to 100
        buffer_options['alpha_q'] = 100
    end

    if extension == 'tiff' then
        -- Strip all metadata (EXIF, XMP, IPTC)
        buffer_options['strip'] = true
        -- Set quality (default is 85)
        buffer_options['Q'] = server.resolve_quality(params, extension)
        -- Set the tiff compression
        buffer_options['compression'] = 'jpeg'
    end

    if extension == 'gif' then
        -- Set the format option to hint the file type.
        buffer_options['format'] = extension
    end

    return buffer_options
end

-- Determines the appropriate mime type (from list of hardcoded values)
-- using the provided extension.
--
-- @param extension Image extension.
-- @return The mime type.
function server.extension_to_mime_type(extension)
    local mime_types = {
        gif = 'image/gif',
        jpg = 'image/jpeg',
        png = 'image/png',
        webp = 'image/webp',
        tiff = 'image/tiff'
    }

    return mime_types[extension]
end

-- Output the final image.
--
-- @param image The final image.
-- @param args The URL query arguments.
function server.output_image(image, args)
    -- Determine image extension from the libvips loader
    local extension = utils.determine_image_extension(args.loader)

    -- Does this image have an alpha channel?
    local has_alpha = utils.has_alpha(image)

    if args.output ~= nil and server.is_extension_allowed(args.output) then
        extension = args.output
    elseif (has_alpha and extension ~= 'png' and extension ~= 'webp' and extension ~= 'gif')
            or not server.is_extension_allowed(extension) then
        -- We force the extension to PNG if:
        -- - The image has alpha and doesn't have the right extension to output alpha.
        --   (useful for shape masking and letterboxing)
        -- - The input extension is not allowed for output.
        extension = 'png'
    end

    --  Write the image to a formatted string
    local buffer = image:write_to_buffer('.' .. extension, server.get_buffer_options(args, extension))

    local mime_type = server.extension_to_mime_type(extension)

    local max_age = 60 * 60 * 24 * 31 -- 31 days
    ngx.status = ngx.HTTP_OK
    ngx.header['Expires'] = ngx.http_time(ngx.time() + max_age)
    ngx.header['Cache-Control'] = 'max-age=' .. max_age

    ngx.header['X-Images-Api'] = '4'

    if args.encoding ~= nil and args.encoding == 'base64' then
        ngx.header['Content-Type'] = 'text/plain'
        ngx.print(string.format("data:%s;base64,%s", mime_type, ngx.encode_base64(buffer)))
    else
        ngx.header['Content-Length'] = #buffer
        ngx.header['Content-Type'] = mime_type

        local file_name = 'image.' .. extension

        -- https://tools.ietf.org/html/rfc2183
        if args.filename ~= nil and
                args.filename ~= '' and
                not args.filename:match("%W") and
                string.len(args.filename .. '.' .. extension) <= 78 then
            file_name = args.filename .. '.' .. extension
        end

        if args.download ~= nil then
            ngx.header['Content-Disposition'] = 'attachment; filename=' .. file_name
        else
            ngx.header['Content-Disposition'] = 'inline; filename=' .. file_name
        end

        ngx.print(buffer)
    end
end

return server