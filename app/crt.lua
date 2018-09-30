local config = require "config"
local template = require "resty.template"
local ngx = ngx
local string = string
local os = os
local io = io

ngx.status = ngx.HTTP_OK
ngx.header["Content-Type"] = "text/html"

local args, args_err = ngx.req.get_uri_args()

function file_exists(name)
   local f=io.open(name,"r")
   if f~=nil then io.close(f) return true else return false end
end

if args.md5 ~= nil then
  ngx.header["Access-Control-Allow-Origin"] = "*"
  ngx.header["Content-Type"] = "text/plain"
  if string.len(args.md5) == 32 and args.md5 == string.match(args.md5, "[a-f0-9]*") then
    local path = "/dev/shm/proxy_cache/" .. string.sub(args.md5,32,32) .. "/" .. string.sub(args.md5,30,31) .. "/" .. args.md5
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
  template.render("cache-removal.html", config.template)
end