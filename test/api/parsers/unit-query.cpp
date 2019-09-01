#include <catch2/catch.hpp>

#include "../base.h"

#include <vips/vips8>

using Catch::Matchers::StartsWith;
using vips::VImage;

TEST_CASE("query ignore", "[query]") {
    SECTION("encoding") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "encoding=base64";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(buffer, !StartsWith("data:image/png;base64,"));
    }

    SECTION("default") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "default=ory.weserv.nl/lichtenstein.jpg";

        Status status = check_file_status(test_image, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("errorredirect (deprecated)") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "errorredirect=ory.weserv.nl/lichtenstein.jpg";

        Status status = check_file_status(test_image, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("filename") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "filename=pixel";

        Status status = check_file_status(test_image, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }

    SECTION("filename") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "url=ory.weserv.nl/lichtenstein.jpg";

        Status status = check_file_status(test_image, params);

        CHECK(status.ok());
        CHECK(status.code() == 200);
    }
}
