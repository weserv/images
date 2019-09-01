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
=== TEST 1: GIF output
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv on;
        weserv_mode file;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif
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


=== TEST 2: JSON output
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv on;
        weserv_mode file;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?output=json
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
!Content-Disposition
--- response_body_like: ^.*"format":"gif","width":1,"height":1,.*$
--- no_error_log
[error]
[warn]


=== TEST 3: base64 output
--- http_config eval: $::HttpConfig
--- config
    location /images {
        weserv on;
        weserv_mode file;
        alias $TEST_NGINX_HTML_DIR;
    }
--- request
    GET /images/test.gif?encoding=base64
--- user_files eval
">>> test.gif
$::TestGif"
--- response_headers
!Content-Disposition
--- response_body_like: ^data:image/gif;base64,.*$
--- no_error_log
[error]
[warn]
