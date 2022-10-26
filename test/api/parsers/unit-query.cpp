#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using Catch::Matchers::StartsWith;
using vips::VImage;

TEST_CASE("query ignore", "[query]") {
    SECTION("encoding") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "encoding=base64";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));
    }

    SECTION("default") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "default=ory.weserv.nl/lichtenstein.jpg";

        Status status = process_file(test_image, nullptr, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("errorredirect (deprecated)") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "errorredirect=ory.weserv.nl/lichtenstein.jpg";

        Status status = process_file(test_image, nullptr, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("filename") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "filename=pixel";

        Status status = process_file(test_image, nullptr, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("url") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "url=ory.weserv.nl/lichtenstein.jpg";

        Status status = process_file(test_image, nullptr, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    // https://github.com/weserv/images/issues/279
    SECTION("option key as filename") {
        auto test_image = fixtures->input_jpg;
        auto params = "filename=flip";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));
        CHECK_THAT(image, is_similar_image(test_image));
    }

    // https://github.com/weserv/images/issues/358
    SECTION("non-API key") {
        auto test_image = fixtures->input_jpg;
        auto params = "v=<UNIX_EPOCH>&w=200";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 200);
    }
}
