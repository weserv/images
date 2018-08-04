return {
    -- Template options
    template = {
        name = "API 4 - GitHub, DEMO",
        url = "images.weserv.nl",
        args = "",
        example_image = "ory.weserv.nl/lichtenstein.jpg",
        example_transparent_image = "ory.weserv.nl/transparency_demo.png",
        example_smartcrop_image = "ory.weserv.nl/zebra.jpg"
    },

    -- Client options
    client = {
        -- User agent for this client
        user_agent = "Mozilla/5.0 (compatible; ImageFetcher/8.0; +http://images.weserv.nl/)",
        -- Sets the connect timeout thresold, send timeout threshold, and read timeout threshold,
        -- respetively, in milliseconds.
        timeouts = {
            connect = 5000,
            send = 5000,
            read = 15000,
        },
        -- Number describing the max image size to receive (in bytes). Use 0 for no limits.
        max_image_size = 104857600, -- 100 MB
        -- Number describing the maximum number of allowed redirects.
        max_redirects = 10,
        -- Allowed mime types. Use empty table to allow all mime types
        allowed_mime_types = {
            --[[["image/jpeg"] = "jpg",
            ["image/png"] = "png",
            ["image/gif"] = "gif",
            ["image/bmp"] = "bmp",
            ["image/tiff"] = "tiff",
            ["image/webp"] = "webp",
            ["image/x-icon"] = "ico",
            ["image/vnd.microsoft.icon"] = "ico",]]
        }
    },

    -- Throttler options
    throttler = {
        -- Redis driver
        redis = {
            scheme = "tcp",
            host = "127.0.0.1",
            port = 6379,
            timeout = 1000, -- 1 sec
            -- The max idle timeout (in ms) when the connection is in the pool
            max_idle_timeout = 10000,
            -- The maximal size of the pool for every nginx worker process
            pool_size = 100
        },
        allowed_requests = 700, -- 700 allowed requests
        minutes = 3, --  In 3 minutes
        prefix = "c", -- Cache key prefix
        whitelist = {
            ["1.2.3.4"] = true, -- Local IP
            ["127.0.0.1"] = true, -- Local IP
        },
        policy = {
            ban_time = 60, -- If exceed, ban for 60 minutes
            cloudflare = {
                enabled = false, -- Is CloudFlare enabled?
                email = "",
                auth_key = "",
                zone_id = "",
                mode = "block" -- The action to apply if the IP get's banned
            }
        }
    }
}