#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("gamma", "[gamma]") {
    SECTION("default value") {
        auto test_image = fixtures->input_jpg_with_gamma_holiness;
        auto expected_image = fixtures->expected_dir + "/gamma-2.2.jpg";

        // Above q=90, libvips will write 4:4:4, i.e. no subsampling of Cr and
        // Cb
        auto params = "gam=true&q=95";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 258);
        CHECK(image.height() == 222);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("value of 3") {
        auto test_image = fixtures->input_jpg_with_gamma_holiness;
        auto expected_image = fixtures->expected_dir + "/gamma-3.0.jpg";

        // Above q=90, libvips will write 4:4:4, i.e. no subsampling of Cr and
        // Cb
        auto params = "gam=3&q=95";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 258);
        CHECK(image.height() == 222);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image = fixtures->expected_dir + "/gamma-alpha.png";
        auto params = "w=320&gam=true";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("invalid") {
        auto test_image = fixtures->input_jpg;
        auto params = "gam=1000000000";

        VImage image = process_file<VImage>(test_image, params);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
