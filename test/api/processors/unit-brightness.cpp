#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("brightness", "[brightness]") {
    SECTION("increase") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/brightness-increase.jpg";
        auto params = "w=320&h=240&fit=cover&bri=30";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("decrease") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/brightness-decrease.jpg";
        auto params = "w=320&h=240&fit=cover&bri=-30";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image = fixtures->expected_dir + "/brightness-trans.png";
        auto params = "w=320&h=240&fit=cover&bri=30";

        std::string buffer;
        std::string extension;
        std::tie(buffer, extension) = process_file(test_image, params);

        CHECK(extension == ".png");

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("invalid") {
        auto test_image = fixtures->input_jpg;
        auto params = "bri=100000000";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
