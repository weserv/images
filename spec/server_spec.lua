local vips = require "vips"
local utils = require "weserv.helpers.utils"
local fixtures = require "spec.fixtures"

describe("server", function()
    local old_ngx = _G.ngx
    local server

    setup(function()
        local stubbed_ngx = {
            -- luacheck: globals ngx._body
            _body = '',
            header = {},
        }
        stubbed_ngx.print = function(s)
            stubbed_ngx._body = stubbed_ngx._body .. s
        end
        stubbed_ngx.say = function(s)
            stubbed_ngx._body = stubbed_ngx._body .. s .. '\n'
        end

        -- Busted requires explicit _G to access the global environment
        _G.ngx = setmetatable(stubbed_ngx, { __index = old_ngx })

        server = require "weserv.server"
    end)

    teardown(function()
        _G.ngx = old_ngx
    end)

    after_each(function()
        -- Clear nginx headers and body after each test
        _G.ngx.header = {}
        _G.ngx._body = ''
    end)

    describe("test nginx headers", function()
        it("jpg", function()
            local test_image = vips.Image.black(1, 1)
            server.output(test_image, {
                output = 'jpg'
            })

            assert.truthy(ngx.header['Expires'])
            assert.truthy(ngx.header['Cache-Control'])
            assert.equal(#ngx._body, ngx.header['Content-Length'])
            assert.equal('image/jpeg', ngx.header['Content-Type'])
            assert.equal('inline; filename=image.jpg', ngx.header['Content-Disposition'])
        end)

        it("base64 encoding", function()
            local test_image = vips.Image.black(1, 1)
            server.output(test_image, {
                output = 'jpg',
                encoding = 'base64'
            })

            assert.truthy(ngx.header['Expires'])
            assert.truthy(ngx.header['Cache-Control'])
            assert.falsy(ngx.header['Content-Length'])
            assert.falsy(ngx.header['Content-Disposition'])
            assert.equal('text/plain', ngx.header['Content-Type'])

            local base64_start = 'data:image/jpeg;base64'
            assert.True(ngx._body:sub(1, #base64_start) == base64_start)
        end)

        it("content disposition attachment", function()
            local test_image = vips.Image.black(1, 1)
            server.output(test_image, {
                output = 'jpg',
                download = '1'
            })

            assert.equal('attachment; filename=image.jpg', ngx.header['Content-Disposition'])
        end)

        it("filename", function()
            local test_image = vips.Image.black(1, 1)
            server.output(test_image, {
                output = 'jpg',
                filename = 'foobar'
            })

            assert.equal('inline; filename=foobar.jpg', ngx.header['Content-Disposition'])
        end)
    end)

    describe("test output image", function()
        it("jpg", function()
            local test_image = vips.Image.new_from_file(fixtures.input_jpg, {
                access = 'sequential'
            })
            server.output(test_image, {
                output = 'jpg',
                loader = 'VipsForeignLoadJpegFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('jpegload_buffer', buffer_image:get('vips-loader'))
            assert.False(utils.has_alpha(buffer_image))
        end)

        it("png", function()
            local test_image = vips.Image.new_from_file(fixtures.input_jpg, {
                access = 'sequential'
            })
            server.output(test_image, {
                output = 'png',
                loader = 'VipsForeignLoadJpegFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('pngload_buffer', buffer_image:get('vips-loader'))
            assert.False(utils.has_alpha(buffer_image))
        end)

        it("force png", function()
            local test_image = vips.Image.new_from_file(fixtures.input_jpg, {
                access = 'sequential'
            })
            -- Add alpha channel to the jpg
            test_image = test_image .. 255

            server.output(test_image, {
                loader = 'VipsForeignLoadJpegFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('pngload_buffer', buffer_image:get('vips-loader'))
            assert.True(utils.has_alpha(buffer_image))
        end)

        it("default png alpha", function()
            local test_image = vips.Image.new_from_file(fixtures.input_svg, {
                access = 'sequential'
            })

            server.output(test_image, {
                loader = 'VipsForeignLoadSvgFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('pngload_buffer', buffer_image:get('vips-loader'))
            assert.True(utils.has_alpha(buffer_image))
        end)

        it("default png non-alpha", function()
            local test_image = vips.Image.new_from_file(fixtures.input_svg, {
                access = 'sequential'
            })
            -- Flatten out alpha
            test_image = test_image:flatten()

            server.output(test_image, {
                loader = 'VipsForeignLoadSvgFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('pngload_buffer', buffer_image:get('vips-loader'))
            assert.False(utils.has_alpha(buffer_image))
        end)

        it("gif", function()
            -- magicksave was added in libvips 8.7
            if vips.version.at_least(8, 7) then
                local test_image = vips.Image.new_from_file(fixtures.input_png_with_grey_alpha, {
                    access = 'sequential'
                })
                server.output(test_image, {
                    output = 'gif',
                    loader = 'VipsForeignLoadPng'
                })

                local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                    access = 'sequential'
                })

                assert.equal('gifload_buffer', buffer_image:get('vips-loader'))
                assert.True(utils.has_alpha(buffer_image))
            end
        end)

        it("tiff", function()
            local test_image = vips.Image.new_from_file(fixtures.input_tiff, {
                access = 'sequential'
            })
            server.output(test_image, {
                output = 'tiff',
                loader = 'VipsForeignLoadTiffFile'
            })

            local buffer_image = vips.Image.new_from_buffer(ngx._body, "", {
                access = 'sequential'
            })

            assert.equal('tiffload_buffer', buffer_image:get('vips-loader'))
            assert.False(utils.has_alpha(buffer_image))
        end)
    end)

    it("test buffer options", function()
        assert.are.same({
            strip = true,
            Q = 85,
            interlace = true,
            optimize_coding = true
        }, server.get_buffer_options({ il = '1' }, 'jpg'))
        assert.are.same({
            interlace = true,
            compression = 6,
            filter = 0xF8
        }, server.get_buffer_options({ il = '1', filter = '1' }, 'png'))
        assert.are.same({
            strip = true,
            Q = 85,
            alpha_q = 100
        }, server.get_buffer_options({}, 'webp'))
        assert.are.same({
            strip = true,
            Q = 85,
            compression = 'jpeg'
        }, server.get_buffer_options({}, 'tiff'))
        assert.are.same({
            format = 'gif',
        }, server.get_buffer_options({}, 'gif'))
    end)

    it("test resolve quality", function()
        assert.equal(1, server.resolve_quality({ q = '1' }, 'jpg'))
        assert.equal(100, server.resolve_quality({ q = '100' }, 'jpg'))
        assert.equal(85, server.resolve_quality({ q = '0' }, 'jpg'))
        assert.equal(0, server.resolve_quality({ level = '0' }, 'png'))
        assert.equal(9, server.resolve_quality({ level = '9' }, 'png'))
        assert.equal(6, server.resolve_quality({ level = '10' }, 'png'))
    end)
end)