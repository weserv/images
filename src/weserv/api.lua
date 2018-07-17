local vips = require "vips"
local client = require "weserv.client"
local utils = require "weserv.helpers.utils"
local ngx = ngx
local os = os
local type = type
local next = next
local table = table
local pcall = pcall
local string = string
local ipairs = ipairs
local tonumber = tonumber
local setmetatable = setmetatable

local api = {}
api.__index = api

-- Instantiate a API object.
--
-- @param config The config
local function new(config)
    local self = {
        config = config,
        manipulators = {}
    }
    return setmetatable(self, api)
end

-- Register a manipulator to be used.
--
-- @param manipulator The manipulator
function api:add_manipulator(manipulator)
    if manipulator ~= nil and type(manipulator.process) == 'function' then
        table.insert(self.manipulators, manipulator)
    end
end

-- Register some manipulators to be used.
--
-- @param manipulators The manipulators
function api:add_manipulators(manipulators)
    for _, manipulator in ipairs(manipulators) do
        if manipulator ~= nil and type(manipulator.process) == 'function' then
            table.insert(self.manipulators, manipulator)
        end
    end
end

-- Get the options to pass on to the load operation.
--
-- @param args The URL query arguments
-- @return Any options to pass on to the load operation + embedded options
function api.get_load_options(args)
    local load_options = {
        access = args.access_method
    }

    local page = tonumber(args.page)
    local loader = args.loader

    local embedded_options = ''

    -- In order to pass the page property to the correct loader
    -- we check if the loader permits a page property.
    if page ~= nil and page >= 0 and page <= 100000 and
            (loader == 'VipsForeignLoadPdfFile'
                    or loader == 'VipsForeignLoadTiffFile'
                    or loader == 'VipsForeignLoadMagickFile') then
        load_options['page'] = page

        -- Add page to the embedded options
        -- Useful for the thumbnail operator
        embedded_options = string.format("[page=%d]", page)
    end

    return load_options, embedded_options
end

-- Start the API.
--
-- @param args The URL query arguments
-- @return Final image
function api:run(args)
    if next(self.manipulators) == nil then
        return nil, { status = ngx.HTTP_INTERNAL_SERVER_ERROR, message = '500 Internal Server Error - Attempted to run images.weserv.nl without any manipulator(s).' }
    end

    local uri = utils.clean_uri(args.url)

    local client = client.new(self.config.client)
    local res, err = client:request_uri(uri)

    if not res then
        return nil, {
            status = ngx.HTTP_NOT_FOUND,
            message = err
        }
    end

    -- Save our temporary file name
    args.tmp_file_name = res.tmpfile

    -- Don't use sequential mode read, if we're doing a trim.
    -- (it will scan the whole image once to find the crop area)
    args.access_method = args.trim ~= nil and 'random' or 'sequential'

    -- Find the name of the load operation vips will use to load a file
    args.loader = utils.find_load(res.tmpfile)

    -- We can't output to GIF yet. (Requires libvips 8.7)
    if (args.loader ~= nil and args.loader == 'VipsForeignLoadGifFile') or
            (args.output ~= nil and args.output == 'gif') then
        os.remove(res.tmpfile)

        return nil, {
            status = ngx.HTTP_MOVED_TEMPORARILY,
            message = 'Can\'t output to gif yet. (Requires libvips 8.7)'
        }
    end

    local load_options, embedded_options = self.get_load_options(args)

    -- Save embedded options
    args.embedded_options = embedded_options

    local image
    local success, err = pcall(function()
        image = vips.Image.new_from_file(args.tmp_file_name .. args.embedded_options, load_options)
    end)

    if not success then
        os.remove(res.tmpfile)

        return nil, {
            status = ngx.HTTP_BAD_REQUEST,
            message = '400 Bad Request - Image not readable. Is it a valid image?'
        }
    end

    -- Put common variables in the parameters
    args.is_premultiplied = false

    -- Calculate the angle of rotation and need-to-flip for the given exif orientation and parameters
    args.rotation, args.flip, args.flop = utils.resolve_rotation_and_flip(image, args)

    return self:next(image, args)
end

-- Runs the next manipulator
--
-- @param image The image
-- @param args The URL query arguments
function api:next(image, args)
    -- Pick each piece of manipulator off in order.
    local manipulator = table.remove(self.manipulators, 1)

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

return {
    new = new,
    __object = api
}