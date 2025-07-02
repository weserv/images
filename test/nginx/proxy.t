#!/usr/bin/env perl

use Test::Nginx::Socket;
use Test::Nginx::Util qw($ServerPort $ServerAddr);
use IO::Compress::Gzip qw(gzip);

plan tests => repeat_each() * (blocks() * 6 - 1);

$ENV{TEST_NGINX_HTML_DIR} ||= html_dir();
$ENV{TEST_NGINX_URI} = "http://$ServerAddr:$ServerPort";
$ENV{TEST_NGINX_SVG} = '<svg viewBox="0 0 1 1"></svg>';

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

our $TestSvgGzip;
gzip \$ENV{TEST_NGINX_SVG} => \$TestSvgGzip;

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
        default               0;
        "~*\+http://wsrv.nl/" 1;
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
        weserv_user_agent "Mozilla/5.0 (compatible; ImageFetcher/9.0; +http://wsrv.nl/)";
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
        proxy_buffering off;
        proxy_pass http://unix:proxy_test.sock;
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/chunked&output=json"
--- tcp_listen: proxy_test.sock
--- tcp_no_close
--- tcp_reply eval
sub {
    return ["HTTP/1.1 200 OK\r\n",
            "Transfer-Encoding: chunked\r\n",
            "\r\n",
            sprintf("%x", length $ENV{TEST_NGINX_SVG}) . "\r\n",
            $ENV{TEST_NGINX_SVG} . "\r\n",
            "0\r\n",
            "\r\n"];
}
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"format":"svg","width":1,"height":1,.*$
--- no_error_log
[error]
[warn]


=== TEST 6: gzip-compressed SVG
--- http_config eval: $::HttpConfig
--- config
    location /gzip {
        add_header Content-Encoding 'gzip';
        alias $TEST_NGINX_HTML_DIR;
    }

    location /images {
        weserv proxy;
    }
--- user_files eval
">>> test.svgz
$::TestSvgGzip"
--- request eval
['GET /gzip/test.svgz', "GET /images?url=$ENV{TEST_NGINX_URI}/gzip/test.svgz&output=json"]
--- response_headers eval
['Content-Encoding: gzip', 'Content-Type: application/json']
--- response_body_like eval
['^\037\213', '^.*"format":"svg","width":1,"height":1,.*$']
--- no_error_log
[error]
[warn]
