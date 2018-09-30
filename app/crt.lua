local config = require "config"
local template = require "resty.template"
local ngx = ngx
local type = type
local os = os
local io = io

ngx.status = ngx.HTTP_OK
ngx.header["Content-Type"] = "text/html"

local args, args_err = ngx.req.get_uri_args()

if args_err == "truncated" then
    ngx.status = ngx.HTTP_BAD_REQUEST
    ngx.header["Content-Type"] = "text/plain"
    ngx.say("400 Bad Request - Request arguments limit is exceeded. A maximum of 100 request arguments are parsed.")
end

local function file_exists(name)
    local f = io.open(name, "r")
    if f ~= nil then
        io.close(f)
        return true
    else
        return false
    end
end

if ngx.var.request_method == 'POST' then
  ngx.header["Access-Control-Allow-Origin"] = "*"
  ngx.header["Content-Type"] = "text/plain"

  if args.md5 ~= nil and type(args.md5) == "string" then
    if #args.md5 == 32 and args.md5 == args.md5:match("[a-f0-9]*") then
        local path = "/dev/shm/proxy_cache/" .. args.md5:sub(32, 32) .. "/" .. args.md5:sub(30, 31) .. "/" .. args.md5
        if file_exists(path) then
            if os.remove(path) then
                ngx.print("removed")
            else
                ngx.print("error")
            end
        else
            ngx.print("not found")
        end
    else
        ngx.print("invalid")
    end
  else
      ngx.print("invalid")
  end
else
    template.render("cache-removal.html", config.template)
end