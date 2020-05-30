#!/usr/bin/env perl

use Test::Nginx::Socket;
use Test::Nginx::Util qw($ServerPort $ServerAddr);

plan tests => repeat_each() * (blocks() * 5);

$ENV{TEST_NGINX_HTML_DIR} ||= html_dir();
$ENV{TEST_NGINX_URI} = "http://$ServerAddr:$ServerPort";

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

no_long_string();
#no_diff();

run_tests();

__DATA__
=== TEST 1: punycode domain names
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv on;
        weserv_mode proxy;
    }
--- request
    GET /images?url=http://♥.localhost
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The hostname of the origin is unresolvable.*$
--- error_code: 404
--- error_log
no resolver defined to resolve xn--g6h.localhost
--- no_error_log
[warn]


=== TEST 2: skip percent encode for reserved characters (RFC 3986)
--- http_config
    error_log logs/error.log debug;

    map $request_uri $is_reserved {
        default                 0;
        "/r/!*'();:@=+$,[]-_.~" 1;
    }
--- config
    location ~ ^/r/ {
        default_type text/plain;
        if ($is_reserved) {
            return 418 "418 I'm a teapot\n";
        }
        return 400 "400 Bad Request\n";
    }

    location /images {
        weserv on;
        weserv_mode proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/r/\x21\x2a\x27\x28\x29\x3b\x3a\x40\x3d\x2b\x24\x2c\x5b\x5d\x2d\x5f\x2e\x7e"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 3: percent encode special characters
--- http_config
    error_log logs/error.log debug;

    map $request_uri $is_special {
        default                          0;
        "/s/%22%3C%3E%5C%5E%60%7B%7C%7D" 1;
    }
--- config
    location ~ ^/s/ {
        default_type text/plain;
        if ($is_special) {
            return 418 "418 I'm a teapot\n";
        }
        return 400 "400 Bad Request\n";
    }

    location /images {
        weserv on;
        weserv_mode proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/s/\x22\x3c\x3e\x5c\x5e\x60\x7b\x7c\x7d"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 4: percent decode only once
--- http_config
    error_log logs/error.log debug;
    map_hash_bucket_size 128;

    map $request_uri $is_escaped {
        default                                                             0;
        "/esc/%2D%2E%5F%7E%21%24%27%28%29%2A%2B%2C%3B%3D%3A%2F%3F%23%40%25" 1;
    }
--- config
    location ~ ^/esc/ {
        default_type text/plain;
        if ($is_escaped) {
            return 418 "418 I'm a teapot\n";
        }
        return 400 "400 Bad Request\n";
    }

    location /images {
        weserv on;
        weserv_mode proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/esc/%252D%252E%255F%257E%2521%2524%2527%2528%2529%252A%252B%252C%253B%253D%253A%252F%253F%2523%2540%2525"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]
