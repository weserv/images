#!/usr/bin/env perl

use Test::Nginx::Socket;
use Test::Nginx::Util qw($ServerPort $ServerAddr);

plan tests => repeat_each() * (blocks() * 5);

$ENV{TEST_NGINX_URI} = "http://$ServerAddr:$ServerPort";

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

no_long_string();
#no_diff();

run_tests();

__DATA__
=== TEST 1: connect timeout
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv proxy;
        weserv_connect_timeout 1s;
    }
--- request
    GET /images?url=10.255.255.1
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":404,"message":"The requested URL timed out".*$
--- error_code: 404
--- error_log
[error]
--- no_error_log
[warn]


=== TEST 2: read timeout
--- http_config eval: $::HttpConfig
--- config
    location /sleep {
        proxy_buffering off;
        proxy_pass http://unix:timeout_test.sock;
    }

    location /images {
        weserv proxy;
        weserv_read_timeout 1s;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/sleep"
--- response_headers
Content-Type: application/json
--- tcp_listen: timeout_test.sock
--- tcp_no_close
--- tcp_reply eval
"HTTP/1.1 204 No Content\r\n\r\n"
--- tcp_reply_delay: 2s
--- response_body_like: ^.*"code":404,"message":"The requested URL timed out".*$
--- error_code: 404
--- error_log
[error]
--- no_error_log
[warn]
