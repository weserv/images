local config = require "config"
local template = require "resty.template"
local ngx = ngx

ngx.status = ngx.HTTP_OK
ngx.header["Content-Type"] = "text/html"

template.render("cache-removal.html", config.template)