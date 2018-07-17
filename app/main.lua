local weserv = require("weserv")
local config = require("config")

local app = weserv.new(config)
app:add_manipulators({
    require "weserv.manipulators.trim",
    require "weserv.manipulators.thumbnail",
    require "weserv.manipulators.orientation",
    require "weserv.manipulators.crop",
    require "weserv.manipulators.letterbox",
    require "weserv.manipulators.shape",
    require "weserv.manipulators.brightness",
    require "weserv.manipulators.contrast",
    require "weserv.manipulators.gamma",
    require "weserv.manipulators.sharpen",
    require "weserv.manipulators.filter",
    require "weserv.manipulators.blur",
    require "weserv.manipulators.background"
})
app:run()