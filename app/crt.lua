local config = require "config"
local template = require "resty.template"
local redis = require "resty.redis"
local ngx = ngx
local type = type
local pairs = pairs
local string = string

local ip_address = ngx.var.http_x_forwarded_for or ngx.var.remote_addr

-- Be careful: keys and values are unescaped according to URI escaping rules.
local args, args_err = ngx.req.get_uri_args()

ngx.status = ngx.HTTP_OK
ngx.header["Content-Type"] = "text/html"

template.render("cache-removal.html", config.template)