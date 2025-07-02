#!/usr/bin/env perl

use Test::Nginx::Socket;
use Test::Nginx::Util qw($ServerPort $ServerAddr);

plan tests => repeat_each() * (blocks() * 5);

$ENV{TEST_NGINX_ADDR} = $ServerAddr;
$ENV{TEST_NGINX_URI} = "http://$ServerAddr:$ServerPort";

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

no_long_string();
#no_diff();

run_tests();

__DATA__
=== TEST 1: invalid uri
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv proxy;
    }
--- request
    GET /images?url=http:\\foobar
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":400,"message":"Unable to parse URI".*$
--- error_code: 400
--- no_error_log
[error]
[warn]


=== TEST 2: redirection loop
--- http_config eval: $::HttpConfig
--- config
    location /302 {
        default_type text/plain;
        return 302 "$TEST_NGINX_URI/302";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/302"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"Will not follow a redirection to itself".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 3: max redirects
--- http_config eval: $::HttpConfig
--- config
    location /1 {
        default_type text/plain;
        return 302 "$TEST_NGINX_URI/2";
    }

    location /2 {
        default_type text/plain;
        return 302 "$TEST_NGINX_URI/redirect";
    }

    location /redirect {
        default_type text/plain;
        return 302 "$TEST_NGINX_URI/1";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/redirect"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"Will not follow more than 10 redirects".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 4: non 200 status
--- http_config eval: $::HttpConfig
--- config
    location /418 {
        default_type text/plain;
        return 418 "418 I'm a teapot\n";
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/418"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL returned error: 418".*$
--- error_code: 404
--- no_error_log
[error]
[warn]


=== TEST 5: big response
--- http_config eval: $::HttpConfig
--- config
    location /200 {
        default_type text/plain;
        return 200 "200 OK\n";
    }

    location /images {
        weserv proxy;
        weserv_max_size 5;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/200"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The image is too large to be downloaded. Max image size: 5 bytes".*$
--- error_code: 404
--- error_log
[error]
--- no_error_log
[warn]

=== TEST 6: IP address block
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv_deny_ip $TEST_NGINX_ADDR;
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/image.png"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":400,"message":"IP address blocked by policy".*$
--- error_code: 400
--- no_error_log
[error]
[warn]
