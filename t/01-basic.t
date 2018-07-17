use Test::Nginx::Socket;
use Cwd qw(cwd);

plan tests => repeat_each() * (blocks() * 4);

my $pwd = cwd();

$ENV{TEST_NGINX_RESOLVER} = '8.8.8.8';
$ENV{TEST_NGINX_PWD} ||= $pwd;
$ENV{TEST_COVERAGE} ||= 0;

our $HttpConfig = qq{
    lua_package_path "$pwd/app/?.lua;$pwd/src/?.lua;;";
    error_log logs/error.log debug;

    init_by_lua_block {
        local vips = require "vips"
        -- libvips caching is not needed
        vips.cache_set_max(0)

        if $ENV{TEST_COVERAGE} == 1 then
            jit.off()
            require("luacov.runner").init()
        end
    }
};

no_long_string();
#no_diff();

run_tests();

__DATA__
=== TEST 1: libvips is setup correctly
--- http_config eval: $::HttpConfig
--- config
    location = /a {
        content_by_lua_block {
            local vips = require "vips"
            ngx.say(vips.version.major .. "." .. vips.version.minor .. "." .. vips.version.micro)
        }
    }
--- request
GET /a
--- response_body_like: ^\d.\d.\d$
--- no_error_log
[error]
[warn]


=== TEST 2: HTML template can be read correctly.
--- http_config eval: $::HttpConfig
--- config
    set $weserv_root $TEST_NGINX_PWD;
    set $template_root $TEST_NGINX_PWD/app/views;
    location = /a {
         content_by_lua_file $TEST_NGINX_PWD/app/main.lua;
    }
--- request
GET /a?api=4
--- response_body_like: API 4 - GitHub, DEMO
--- no_error_log
[error]
[warn]

