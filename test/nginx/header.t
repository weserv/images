#!/usr/bin/env perl

use Test::Nginx::Socket;
use Test::Nginx::Util qw($ServerPort $ServerAddr);

plan tests => repeat_each() * (blocks() * 5);

$ENV{TEST_NGINX_HTML_DIR} ||= html_dir();
$ENV{TEST_NGINX_URI} = "http://$ServerAddr:$ServerPort";

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

our $TestGif = unhex(qq{
0x0000:  47 49 46 38 39 61 01 00  01 00 80 01 00 00 00 00  |GIF89a.. ........|
0x0010:  ff ff ff 21 f9 04 01 00  00 01 00 2c 00 00 00 00  |...!.... ...,....|
0x0020:  01 00 01 00 00 02 02 4c  01 00 3b                 |.......L ..;|
});

our $TestGifLength = length($TestGif);

sub unhex {
    my ($input) = @_;
    my $buffer = '';

    for my $l ($input =~ m/:  +((?:[0-9a-f]{2,4} +)+) /gms) {
        for my $v ($l =~ m/[0-9a-f]{2}/g) {
            $buffer .= chr(hex($v));
        }
    }

    return $buffer;
}

sub gif_size {
    my $content = shift;
    return join ' ', unpack("x6v2", $content);
}

no_long_string();
#no_diff();

run_tests();

__DATA__
=== TEST 1: max age can be set
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?maxage=1w
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Cache-Control: public, max-age=604800
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 2: max age cannot be lower than 1 day
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?maxage=1h
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Cache-Control: public, max-age=31536000
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 3: max age cannot be higher than 1 year
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?maxage=2y
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Cache-Control: public, max-age=31536000
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 4: filename can be set
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?filename=test
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Content-Disposition: inline; filename=test.gif
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 5: filename may contain only alphanumeric characters
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?filename=../test
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Content-Disposition: inline; filename=image.gif
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 6: default image
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv proxy;
    }
--- request
    GET /images?url=http:\\foobar&default=https://example.org
--- response_headers
Location: https://example.org/
--- response_body_like: ^.*"code":400,"message":"Unable to parse URI".*$
--- error_code: 302
--- no_error_log
[error]
[warn]

=== TEST 7: unsupported saver
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        weserv_savers jpg;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?output=json
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Content-Type: application/json
--- response_body_like: ^.*"code":400,"message":"Saving to json is disabled. Supported savers: jpg".*$
--- error_code: 400
--- no_error_log
[error]
[warn]

=== TEST 8: rel="canonical" HTTP Header is set
--- http_config eval: $::HttpConfig
--- config
    location /static {
        alias $TEST_NGINX_HTML_DIR;
    }

    location /images {
        weserv proxy;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/static/test.gif"
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers eval
"Link: <$ENV{TEST_NGINX_URI}/static/test.gif>; rel=\"canonical\""
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]

=== TEST 9: $weserv_response_length variable can be used
--- http_config eval: $::HttpConfig
--- config
    location /static {
        alias $TEST_NGINX_HTML_DIR;
    }

    location /images {
        weserv proxy;

        add_header X-Upstream-Response-Length $weserv_response_length;
    }
--- request eval
"GET /images?url=$ENV{TEST_NGINX_URI}/static/test.gif"
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers eval
"X-Upstream-Response-Length: $::TestGifLength"
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]
