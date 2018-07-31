local vips = require "vips"
local utils = require "weserv.helpers.utils"
local ngx = ngx
local type = type
local next = next
local table = table
local pcall = pcall
local string = string
local ipairs = ipairs
local tonumber = tonumber
local setmetatable = setmetatable

--- API module.
-- @module api
local api = {}
api.__index = api

--- Instantiate a API object.
local function new()
    local self = {
        manipulators = {},
        manipulators_queue = {}
    }
    return setmetatable(self, api)
end

--- Register a manipulator to be used.
-- @param manipulator The manipulator
function api:add_manipulator(manipulator)
    if manipulator ~= nil and
            type(manipulator) == "table" and
            type(manipulator.process) == 'function' then
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

    local string_options = ''

    -- In order to pass the page property to the correct loader
    -- we check if the loader permits a page property.
    if page ~= nil and page >= 0 and page <= 100000 and
            (loader == 'VipsForeignLoadPdfFile' or
                    loader == 'VipsForeignLoadTiffFile' or
                    loader == 'VipsForeignLoadMagickFile') then
        load_options.page = page

        -- Add page to the string options
        -- Useful for the thumbnail operator
        string_options = string.format("[page=%d]", page)
    end

    return load_options, string_options
end

--- Runs the next manipulator.
-- @param image The image.
-- @param args The URL query arguments.
function api:next(image, args)
    -- Pick each piece of manipulator off in order from the working queue
    local manipulator = table.remove(self.manipulators_queue, 1)

    if manipulator ~= nil and type(manipulator.process) == 'function' then
        -- Call the manipulator, which may itself call next().
        return manipulator.process(self, image, args)
    else
        -- Reverse premultiplication after all transformations:
        if args.is_premultiplied then
            -- Unpremultiply image alpha and cast pixel values to integer
            image = image:unpremultiply():cast('uchar')
        end

        return image, nil
    end
end

--- Process the image from the temporary file.
-- @param tmpfile A temporary file.
-- @param args The URL query arguments.
-- @return Final image.
function api:process(tmpfile, args)
    if next(self.manipulators) == nil then
        return nil, {
            status = ngx.HTTP_INTERNAL_SERVER_ERROR,
            message = 'Attempted to run images.weserv.nl without any manipulator(s).',
        }
    end

    -- Save our temporary file name
    args.tmp_file_name = tmpfile

    -- Don't use sequential mode read, if we're doing a trim.
    -- (it will scan the whole image once to find the crop area)
    args.access_method = args.trim ~= nil and 'random' or 'sequential'

    -- Find the name of the load operation vips will use to load a file
    -- so that we can work out what options to pass to new_from_file()
    args.loader = vips.Image.find_load(tmpfile)

    if args.loader == nil then
        -- Log image invalid or unsupported errors
        ngx.log(ngx.ERR, 'Image invalid or unsupported: ', vips.verror.get())

        -- No known loader is found, stop further processing
        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = 'Invalid or unsupported image format. Is it a valid image?',
        }
    end

    local load_options, string_options = self.get_load_options(args)

    -- Save string options
    args.string_options = string_options

    local image
    local success, image_err = pcall(function()
        image = vips.Image.new_from_file(args.tmp_file_name .. args.string_options, load_options)
    end)

    if not success then
        -- Log image not readable errors
        ngx.log(ngx.ERR, 'Image not readable: ', image_err)

        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = 'Image not readable. Is it a valid image?',
        }
    end

    -- Put common variables in the parameters
    args.is_premultiplied = false

    -- Calculate the angle of rotation and need-to-flip for the given exif orientation and parameters
    args.rotation, args.flip, args.flop = utils.resolve_rotation_and_flip(image, args)

    -- Empty working queue
    self.manipulators_queue = {}

    -- Fill working queue
    for k, v in ipairs(self.manipulators) do self.manipulators_queue[k] = v end

    -- Run the next manipulator.
    return self:next(image, args)
end

return {
    new = new,
    __object = api
}