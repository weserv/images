#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("trim", "[trim]") {
    SECTION("threshold 25") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image =
            fixtures->expected_dir + "/alpha-layer-1-fill-trim-resize.png";
        auto params = "w=450&h=322&fit=cover&trim=25";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 450);
        CHECK(image.height() == 322);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("16bit with transparency") {
        auto test_image = fixtures->input_png_with_transparency_16bit;
        auto expected_image = fixtures->expected_dir + "/trim-16bit-rgba.png";
        auto params = "w=32&h=32&fit=cover&trim=10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 4);
        CHECK(image.width() == 32);
        CHECK(image.height() == 32);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("skip shrink-on-load") {
        auto test_image = fixtures->input_jpg_overlay_layer_2;
        auto expected_image =
            fixtures->expected_dir + "/alpha-layer-2-trim-resize.jpg";
        auto params = "w=300&trim=10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
        CHECK(!image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("aggressive trim returns original image") {
        auto test_image = fixtures->input_png_overlay_layer_0;
        auto params = "trim=200";

        VImage image = process_file<VImage>(test_image, params);

        // Check if dimensions are unchanged
        CHECK(image.width() == 2048);
        CHECK(image.height() == 1536);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }

    SECTION("small image returns original image") {
        auto test_image = fixtures->input_png_pixel;
        auto params = "trim=200";

        VImage image = process_file<VImage>(test_image, params);

        // Check if dimensions are unchanged
        CHECK(image.width() == 1);
        CHECK(image.height() == 1);
    }

    SECTION("skip height in toilet-roll mode") {
        if (vips_type_find("VipsOperation", "gifload_buffer") == 0 ||
            vips_type_find("VipsOperation", "gifsave_buffer") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&trim=25";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 930);

        // Check if page height is unchanged
        CHECK(vips_image_get_page_height(image.get_image()) == 1050);
    }
}
