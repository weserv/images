#include <catch2/catch.hpp>

#include "../base.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("color", "[color]") {
    SECTION("three digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=ABC";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{170, 187, 204}));
    }

    SECTION("three digit with hash") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=%23ABC";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{170, 187, 204}));
    }

    SECTION("four digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=3ABC";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{170, 187, 204, 51}));
    }

    SECTION("four digit with hash") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=%233ABC";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{170, 187, 204, 51}));
    }

    SECTION("six digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=11FF33";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{17, 255, 51}));
    }

    SECTION("six digit with hash") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=%2311FF33";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{17, 255, 51}));
    }

    SECTION("eight digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=3311FF33";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{17, 255, 51, 51}));
    }

    SECTION("eight digit with hash") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=%233311FF33";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{17, 255, 51, 51}));
    }

    SECTION("named") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=black";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0), Equals(std::vector<double>{0, 0, 0}));
    }

    SECTION("all none hex") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=ZXCZXCMM";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("one none hex") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=0123456X";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("two digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=01";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("five digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=01234";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("nine digit") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=012345678";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("unknown") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=unknown";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }

    SECTION("empty") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "bg=";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK_THAT(image.getpoint(0, 0),
                   Equals(std::vector<double>{0, 0, 0, 0}));
    }
}
