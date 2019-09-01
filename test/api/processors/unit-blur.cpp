#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("blur", "[blur]") {
    SECTION("radius 1") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-1.jpg";
        auto params = "w=320&h=240&fit=cover&blur=1";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("radius 10") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-10.jpg";
        auto params = "w=320&h=240&fit=cover&blur=10";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("radius 0.3") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-0.3.jpg";
        auto params = "w=320&h=240&fit=cover&blur=0.3";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("mild") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-mild.jpg";
        auto params = "w=320&h=240&fit=cover&blur=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image = fixtures->expected_dir + "/blur-trans.png";
        auto params = "w=320&h=240&fit=cover&blur=10";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}
