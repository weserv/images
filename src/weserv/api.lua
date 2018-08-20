local vips = vips
local utils = require "weserv.helpers.utils"
local type = type
local next = next
local pcall = pcall
local str_format = string.format
local ipairs = ipairs
local tonumber = tonumber
local setmetatable = setmetatable
local ngx_log = ngx.log
local ngx_ERR = ngx.ERR
local HTTP_NOT_FOUND = ngx.HTTP_NOT_FOUND
local HTTP_INTERNAL_SERVER_ERROR = ngx.HTTP_INTERNAL_SERVER_ERROR

--- API module.
-- @module api
local api = {}
local mt = { __index = api }

--- Instantiate a API object.
function api.new()
    return setmetatable({
        manipulators = {},
    }, mt)
end

--- Register a manipulator to be used.
-- @param manipulator The manipulator
function api:add_manipulator(manipulator)
    if manipulator ~= nil and
            type(manipulator) == "table" and
            type(manipulator.process) == "function" and
            type(manipulator.should_process) == "function" then
        self.manipulators[#self.manipulators + 1] = manipulator
    end
end

--- Register some manipulators to be used.
-- @param manipulators The manipulators
function api:add_manipulators(manipulators)
    if manipulators ~= nil and
            type(manipulators) == "table" then
        -- Need to retain order, so loop with ipairs.
        for _, manipulator in ipairs(manipulators) do
            self:add_manipulator(manipulator)
        end
    end
end

--- Clear all manipulators
function api:clear_manipulators()
    self.manipulators = {}
end

--- Get the options to pass on to the load operation.
-- @param args The URL query arguments.
-- @return Any options to pass on to the load operation + string options.
function api.get_load_options(args)
    local load_options = {
        access = args.access_method
    }

    local page = tonumber(args.page)
    local loader = args.loader

    local string_options = ""

    -- In order to pass the page property to the correct loader
    -- we check if the loader permits a page property.
    if page ~= nil and page >= 0 and page <= 100000 and
            (loader == "VipsForeignLoadPdfFile" or
                    loader == "VipsForeignLoadTiffFile" or
                    loader == "VipsForeignLoadMagickFile") then
        load_options.page = page

        -- Add page to the string options
        -- Useful for the thumbnail operator
        string_options = str_format("[page=%d]", page)
    end

    return load_options, string_options
end

--- Process the image from the temporary file.
-- @param tmpfile A temporary file.
-- @param args The URL query arguments.
-- @return Final image.
function api:process(tmpfile, args)
    if next(self.manipulators) == nil then
        return nil, {
            status = HTTP_INTERNAL_SERVER_ERROR,
            message = "Attempted to run images.weserv.nl without any manipulator(s).",
        }
    end

    -- Save our temporary file name
    args.tmp_file_name = tmpfile

    -- Don't use sequential mode read, if we're doing a trim.
    -- (it will scan the whole image once to find the crop area)
    args.access_method = args.trim ~= nil and "random" or "sequential"

    -- Find the name of the load operation vips will use to load a file
    -- so that we can work out what options to pass to new_from_file()
    args.loader = vips.Image.find_load(tmpfile)

    if args.loader == nil then
        -- Log image invalid or unsupported errors
        ngx_log(ngx_ERR, "Image invalid or unsupported: ", vips.verror.get())

        -- No known loader is found, stop further processing
        return nil, {
            status = HTTP_NOT_FOUND,
            message = "Invalid or unsupported image format. Is it a valid image?",
        }
    end

    local load_options, string_options = self.get_load_options(args)

    -- Save string options
    args.string_options = string_options

    local image
    local read_success, read_err = pcall(function()
        image = vips.Image.new_from_file(args.tmp_file_name .. args.string_options, load_options)
    end)

    if not read_success then
        -- Log image not readable errors
        ngx_log(ngx_ERR, "Image not readable: ", read_err)

        return nil, {
            status = HTTP_NOT_FOUND,
            message = "Image not readable. Is it a valid image?",
        }
    end

    -- Put common variables in the parameters
    args.is_premultiplied = false
    args.has_alpha = utils.has_alpha(image)

    -- Calculate the angle of rotation and need-to-flip for the given exif orientation and parameters
    args.rotation, args.flip, args.flop = utils.resolve_rotation_and_flip(image, args)

    -- Process all manipulators.
    local success, image_err
    for _, manipulator in ipairs(self.manipulators) do
        -- Should this manipulator process the image?
        if manipulator.should_process(args) then
            -- Wrap it into pcall because we may throw errors.
            success, image_err = pcall(function()
                -- Call the manipulator. If a manipulator can't process the image
                -- an error should be thrown.
                image = manipulator.process(image, args)
            end)

            if not success then
                -- Don't process further if an error has occurred.
                break
            end
        end
    end

    if not success then
        local api_err

        -- lua-vips will throw errors as string types
        if type(image_err) == "string" then
            -- Log libvips errors
            ngx_log(ngx_ERR, "libvips error: ", image_err)

            api_err = {
                status = HTTP_NOT_FOUND,
                message = "libvips error: " .. image_err,
            }
        else
            -- Otherwise we may throw it as a table type which
            -- contains a status and message.
            api_err = image_err
        end

        -- A image manipulation has caused an error, return
        -- the error which may contain information to resolve it.
        return nil, api_err
    else
        -- All image manipulations were successful, reverse
        -- premultiplication after all transformations:
        if args.is_premultiplied then
            -- Unpremultiply image alpha and cast pixel values to integer
            image = image:unpremultiply():cast("uchar")
        end

        -- Return the image without errors
        return image, nil
    end
end

return api