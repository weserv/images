#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("sharpen", "[sharpen]") {
    // Specific radius 10 (sigma 6)
    SECTION("radius 10") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sharpen-10.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=6";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // Specific radius 3 (sigma 1.5) and levels 0.5, 2.5
    SECTION("radius 3") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sharpen-3-0.5-2.5.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=1.5&sharpf=0.5&sharpj=2.5";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // Specific radius 5 (sigma 3.5) and levels 2, 4
    SECTION("radius 5") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sharpen-5-2-4.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=3.5&sharpf=2&sharpj=4";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // Specific radius 5 (sigma 3.5) and levels 4, 8 with alpha channel
    SECTION("radius 5 with transparency") {
        auto test_image = fixtures->input_png_with_transparency;
        auto expected_image = fixtures->expected_dir + "/sharpen-rgba.png";
        auto params = "w=320&h=240&fit=cover&sharp=5&sharpf=4&sharpj=8";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("mild") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sharpen-mild.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=true";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("cmyk") {
        auto test_image = fixtures->input_jpg_with_cmyk_profile;
        auto expected_image = fixtures->expected_dir + "/sharpen-cmyk.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=6";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.interpretation() == VIPS_INTERPRETATION_sRGB);
        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("radius 3 deprecated") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sharpen-3-0.5-2.5.jpg";
        auto params = "w=320&h=240&fit=cover&sharp=0.5,2.5,1.5";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("invalid") {
        auto test_image = fixtures->input_jpg;
        auto params = "sharp=-1,-1,-1,-1";

        VImage image = process_file<VImage>(test_image, params);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
