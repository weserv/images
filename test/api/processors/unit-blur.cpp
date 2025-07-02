#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("blur", "[blur]") {
    SECTION("radius 1") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-1.jpg";
        auto params = "w=320&h=240&fit=cover&blur=1";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("radius 10") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-10.jpg";
        auto params = "w=320&h=240&fit=cover&blur=10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("radius 0.3") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-0.3.jpg";
        auto params = "w=320&h=240&fit=cover&blur=0.3";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("mild") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/blur-mild.jpg";
        auto params = "w=320&h=240&fit=cover&blur=true";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image = fixtures->expected_dir + "/blur-trans.png";
        auto params = "w=320&h=240&fit=cover&blur=10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}
