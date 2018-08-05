describe("utils", function()
    local old_ngx = _G.ngx
    local utils

    setup(function()
        local stubbed_ngx = {
            -- luacheck: globals ngx._logs
            _logs = {},
        }
        stubbed_ngx.log = function(...)
            stubbed_ngx._logs[#stubbed_ngx._logs + 1] = table.concat({ ... }, " ")
        end

        -- Busted requires explicit _G to access the global environment
        _G.ngx = setmetatable(stubbed_ngx, { __index = old_ngx })

        -- Reinitialize the utils package
        package.loaded["weserv.helpers.utils"] = nil
        utils = require "weserv.helpers.utils"
    end)

    teardown(function()
        _G.ngx = old_ngx
    end)

    after_each(function()
        -- Clear logs after each test
        _G.ngx._logs = {}
    end)

    it("test is 16 bit", function()
        assert.True(utils.is_16_bit("rgb16"))
        assert.True(utils.is_16_bit("grey16"))
        assert.False(utils.is_16_bit("srgb"))
    end)

    it("test maximum image alpha", function()
        assert.equal(65535, utils.maximum_image_alpha("rgb16"))
        assert.equal(65535, utils.maximum_image_alpha("grey16"))
        assert.equal(255, utils.maximum_image_alpha("srgb"))
    end)

    it("test temp name", function()
        local tmpfile = utils.tempname("/dev/shm", "imo_")

        assert.truthy(tmpfile:find("/dev/shm/imo_"))
        assert.equal(19, #tmpfile)
        assert(os.remove(tmpfile))

        tmpfile = utils.tempname("/path/does/not/exist", "imo_")
        assert.falsy(tmpfile)
    end)

    it("test clean uri", function()
        assert.equal("https://ory.weserv.nl/lichtenstein.jpg",
            utils.clean_uri("https://ory.weserv.nl/lichtenstein.jpg"))
        assert.equal("https://ory.weserv.nl/lichtenstein.jpg",
            utils.clean_uri("https://ory.weserv.nl/lichtenstein.jpg?errorredirect=example.org"))
        assert.equal("https://ory.weserv.nl/lichtenstein.jpg",
            utils.clean_uri("ssl:ory.weserv.nl/lichtenstein.jpg"))
        assert.equal("http://ory.weserv.nl/lichtenstein.jpg",
            utils.clean_uri("ory.weserv.nl/lichtenstein.jpg"))
        assert.equal("https://ory.weserv.nl/lichtenstein.jpg",
            utils.clean_uri("//ory.weserv.nl/lichtenstein.jpg"))
        assert.equal("https://ory.weserv.nl/lichtenstein.jpg?test=test",
            utils.clean_uri("//ory.weserv.nl/lichtenstein.jpg?test=test&errorredirect=example.org"))
    end)

    it("test parse uri", function()
        assert.are.same({ "http", "ory.weserv.nl", 80, "/", "" },
            { unpack(utils.parse_uri("http://ory.weserv.nl")) })
        assert.are.same({ "http", "ory.weserv.nl", 80, "/", "" },
            { unpack(utils.parse_uri("http://ory.weserv.nl/")) })
        assert.are.same({ "https", "ory.weserv.nl", 443, "/foo/bar", "" },
            { unpack(utils.parse_uri("https://ory.weserv.nl/foo/bar")) })
        assert.are.same({ "https", "ory.weserv.nl", 443, "/foo/bar", "a=1&b=2" },
            { unpack(utils.parse_uri("https://ory.weserv.nl/foo/bar?a=1&b=2")) })
        assert.are.same({ "http", "ory.weserv.nl", 80, "/", "a=1&b=2" },
            { unpack(utils.parse_uri("http://ory.weserv.nl?a=1&b=2")) })
        assert.are.same({ "http", "ory.weserv.nl", 443, "/", "a=1&b=2" },
            { unpack(utils.parse_uri("http://ory.weserv.nl:443/?a=1&b=2")) })
        assert.are.same({ "http", "ory.weserv.nl", 443, "/sub//path/", "" },
            { unpack(utils.parse_uri("http://ory.weserv.nl:443/sub//path/")) })
        assert.equal("https://ory.weserv.nl",
            utils.clean_uri("//ory.weserv.nl"))
        assert.are.same({ nil, "Unable to parse URL" },
            { utils.parse_uri("http:\\ory.weserv.nl") })
        assert.are.same({ nil, "Invalid domain label" },
            { utils.parse_uri("--example--.org") })
        -- Doesn't escape special / reserved characters
        -- in accordance with RFC 3986.
        -- Also, a fragment identifier must be removed from the URI.
        -- See: https://github.com/weserv/images/issues/145 and
        -- https://github.com/weserv/images/issues/144
        assert.are.same({
            "https", "ory.weserv.nl", 443,
            "/-._~!$'()*+,;=:@%",
            ""
        }, {
            unpack(utils.parse_uri("//ory.weserv.nl/-._~!$'()*+,;=:@%#exclude?bar=foo"))
        })
        assert.are.same({
            "https", "ory.weserv.nl", 443,
            "/",
            "bar=-._~!$'()*+,;=:/@%"
        }, {
            unpack(utils.parse_uri("//ory.weserv.nl/?bar=-._~!$'()*+,;=:/@%#exclude"))
        })

        -- Doesn't unescape twice
        -- See: https://github.com/weserv/images/issues/149
        assert.are.same({
            "https", "ory.weserv.nl", 443,
            "/",
            "bar=%2D%2E%5F%7E%21%24%27%28%29%2A%2B%2C%3B%3D%3A%2F%40"
        }, {
            unpack(utils.parse_uri("//ory.weserv.nl/?bar=%2D%2E%5F%7E%21%24%27%28%29%2A%2B%2C%3B%3D%3A%2F%40"))
        })
        assert.are.same({
            "https", "ory.weserv.nl", 443,
            "/%2D%2E%5F%7E%21%24%27%28%29%2A%2B%2C%3B%3D%3A%2F%3F%23%40",
            "bar=foo"
        }, {
            unpack(utils.parse_uri("//ory.weserv.nl/%2D%2E%5F%7E%21%24%27%28%29%2A%2B%2C%3B%3D%3A%2F%3F%23%40?bar=foo"))
        })
    end)

    it("test percent encode", function()
        assert.equal("%22", utils.percent_encode("\""))
        assert.equal("%3C", utils.percent_encode("<"))
        assert.equal("%3E", utils.percent_encode(">"))
        assert.equal("%5B", utils.percent_encode("["))
        assert.equal("%5C", utils.percent_encode("\\"))
        assert.equal("%5D", utils.percent_encode("]"))
        assert.equal("%5E", utils.percent_encode("^"))
        assert.equal("%60", utils.percent_encode("`"))
        assert.equal("%7B", utils.percent_encode("{"))
        assert.equal("%7C", utils.percent_encode("|"))
        assert.equal("%7D", utils.percent_encode("}"))
    end)

    it("test resolve angle rotation", function()
        assert.equal(270, utils.resolve_angle_rotation("-3690"));
        assert.equal(270, utils.resolve_angle_rotation("-450"));
        assert.equal(270, utils.resolve_angle_rotation("-90"));
        assert.equal(90, utils.resolve_angle_rotation("90"));
        assert.equal(90, utils.resolve_angle_rotation("450"));
        assert.equal(90, utils.resolve_angle_rotation("3690"));
        assert.equal(180, utils.resolve_angle_rotation("-3780"));
        assert.equal(180, utils.resolve_angle_rotation("-540"));
        assert.equal(0, utils.resolve_angle_rotation("0"));
        assert.equal(180, utils.resolve_angle_rotation("180"));
        assert.equal(180, utils.resolve_angle_rotation("540"));
        assert.equal(180, utils.resolve_angle_rotation("3780"));
        assert.equal(0, utils.resolve_angle_rotation("invalid"));
        assert.equal(0, utils.resolve_angle_rotation("91"));
    end)

    it("test determine image extension", function()
        assert.equal("jpg", utils.determine_image_extension("VipsForeignLoadJpegFile"));
        assert.equal("png", utils.determine_image_extension("VipsForeignLoadPng"));
        assert.equal("webp", utils.determine_image_extension("VipsForeignLoadWebpFile"));
        assert.equal("tiff", utils.determine_image_extension("VipsForeignLoadTiffFile"));
        assert.equal("gif", utils.determine_image_extension("VipsForeignLoadGifFile"));
        assert.equal("svg", utils.determine_image_extension("VipsForeignLoadSvgFile"));
        assert.equal("unknown", utils.determine_image_extension("invalid"));
    end)

    it("test format bytes", function()
        local base = 1024
        local pow2 = base * 1024
        local pow3 = pow2 * 1024
        local pow4 = pow3 * 1024

        assert.equal("0 B", utils.format_bytes(0));
        assert.equal("1 B", utils.format_bytes(1));
        assert.equal("1023 B", utils.format_bytes(base - 1));
        assert.equal("1 KB", utils.format_bytes(base));
        assert.equal("1024 KB", utils.format_bytes(pow2 - 1));
        assert.equal("1 MB", utils.format_bytes(pow2));
        assert.equal("1024 MB", utils.format_bytes(pow3 - 1));
        assert.equal("1 GB", utils.format_bytes(pow3));
        assert.equal("1024 GB", utils.format_bytes(pow4 - 1));
        assert.equal("1 TB", utils.format_bytes(pow4));
        assert.equal("203.25 MB", utils.format_bytes(213123123));
        assert.equal("19.85 GB", utils.format_bytes(21312312390));
    end)
end)