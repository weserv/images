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
=== TEST 1: follow 302 temporary redirect - relative URI
--- http_config eval: $::HttpConfig
--- config
    location /418 {
        default_type text/plain;
        return 418 "418 I'm a teapot\n";
    }

    location /sub/302 {
        default_type text/plain;
        return 302 "./../418";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/sub/302"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 2: follow 301 permanent redirect - relative URI
--- http_config eval: $::HttpConfig
--- config
    location /418 {
        default_type text/plain;
        return 418 "418 I'm a teapot\n";
    }

    location /301 {
        default_type text/plain;
        return 301 " /418";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/301"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 3: request is send with the appropriate user agent
--- http_config
    error_log logs/error.log debug;

    map $http_user_agent $is_weserv {
        default                        0;
        "~*\+http://images.weserv.nl/" 1;
    }
--- config
    location /user_agent {
        default_type text/plain;
        if ($is_weserv) {
            return 418 "418 I'm a teapot\n";
        }
        return 400 "400 Bad Request\n";
    }

    location /images {
        weserv proxy;
        weserv_user_agent "Mozilla/5.0 (compatible; ImageFetcher/9.0; +http://images.weserv.nl/)";
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/user_agent"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 4: request is send with an referer when redirecting
--- http_config
    error_log logs/error.log debug;

    map $http_referer $good_referer {
        default       0;
        "~*/referer$" 1;
    }
--- config
    location /418 {
        default_type text/plain;
        if ($good_referer) {
            return 418 "418 I'm a teapot\n";
        }
        return 400 "400 Bad Request\n";
    }

    location /referer {
        default_type text/plain;
        return 301 "$TEST_NGINX_URI/418";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/referer"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 5: chunked transfer encoding
--- http_config eval: $::HttpConfig
--- config
    location /chunked {
        default_type image/svg+xml;
        chunked_transfer_encoding on;
        echo '<svg viewBox="0 0 1 1"></svg>';
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/chunked&output=json"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"format":"svg","width":1,"height":1,.*$
--- no_error_log
[error]
[warn]
