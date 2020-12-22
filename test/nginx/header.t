#!/usr/bin/env perl

use Test::Nginx::Socket;

plan tests => repeat_each() * (blocks() * 5);

$ENV{TEST_NGINX_HTML_DIR} ||= html_dir();

our $HttpConfig = qq{
    error_log logs/error.log debug;
};

our $TestGif = unhex(qq{
0x0000:  47 49 46 38 39 61 01 00  01 00 80 01 00 00 00 00  |GIF89a.. ........|
0x0010:  ff ff ff 21 f9 04 01 00  00 01 00 2c 00 00 00 00  |...!.... ...,....|
0x0020:  01 00 01 00 00 02 02 4c  01 00 3b                 |.......L ..;|
});

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
    GET /images/test.gif?maxage=31d
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
Cache-Control: public, max-age=2678400
--- response_body_filters eval
\&::gif_size
--- response_body: 1 1
--- no_error_log
[error]
[warn]


=== TEST 2: max age cannot be lower than 31 days
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv filter;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?maxage=30d
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


=== TEST 3: filename can be set
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


=== TEST 4: filename may contain only alphanumeric characters
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


=== TEST 5: default image
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
