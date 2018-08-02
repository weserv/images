std = "ngx_lua"

globals = {
    "vips",
}

files["spec/**/*.lua"] = {
    std = "ngx_lua+busted",
}
