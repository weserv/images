#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("embed", "[embed]") {
    // TIFF letterbox known to cause rounding errors
    SECTION("tiff") {
        if (vips_type_find("VipsOperation", true_streaming
                                                ? "tiffload_source"
                                                : "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", true_streaming
                                                ? "tiffsave_target"
                                                : "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params = "w=240&h=320&fit=contain&cbg=white";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("tiffload_buffer"));

        CHECK(image.width() == 240);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    // Letterbox TIFF in LAB colourspace onto RGBA background
    SECTION("tiff on rgba") {
        if (vips_type_find("VipsOperation", true_streaming
                                                ? "tiffload_source"
                                                : "tiffload_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff_cielab;
        auto expected_image =
            fixtures->expected_dir + "/embed-lab-into-rgba.png";
        auto params = "w=64&h=128&fit=contain&cbg=80FF6600&output=png";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 64);
        CHECK(image.height() == 128);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // From CMYK to sRGB with white background, not yellow
    SECTION("jpg cmyk to srgb with background") {
        auto test_image = fixtures->input_jpg_with_cmyk_profile;
        auto expected_image = fixtures->expected_dir + "/colourspace.cmyk.jpg";
        auto params = "w=320&h=240&fit=contain&cbg=white";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.interpretation() == VIPS_INTERPRETATION_sRGB);
        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_with_transparency;
        auto expected_image = fixtures->expected_dir + "/embed-4-into-4.png";
        auto params = "w=50&h=50&fit=contain&cbg=white";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 4);
        CHECK(image.width() == 50);
        CHECK(image.height() == 50);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // 16-bit PNG with alpha channel
    SECTION("png 16bit with transparency") {
        auto test_image = fixtures->input_png_with_transparency_16bit;
        auto expected_image = fixtures->expected_dir + "/embed-16bit.png";
        auto params = "w=32&h=16&fit=contain&cbg=white";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 4);
        CHECK(image.width() == 32);
        CHECK(image.height() == 16);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // 16-bit PNG with alpha channel onto RGBA
    SECTION("png 16bit with transparency on rgba") {
        auto test_image = fixtures->input_png_with_transparency_16bit;
        auto expected_image = fixtures->expected_dir + "/embed-16bit-rgba.png";
        auto params = "w=32&h=16&fit=contain";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 4);
        CHECK(image.width() == 32);
        CHECK(image.height() == 16);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // PNG with 2 channels
    SECTION("png 16bit with transparency on rgba") {
        auto test_image = fixtures->input_png_with_grey_alpha;
        auto expected_image = fixtures->expected_dir + "/embed-2channel.png";
        auto params = "w=32&h=16&fit=contain";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 4);
        CHECK(image.width() == 32);
        CHECK(image.height() == 16);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    // Enlarge and embed
    SECTION("enlarge") {
        auto test_image = fixtures->input_png_with_one_color;
        auto expected_image = fixtures->expected_dir + "/embed-enlarge.png";
        auto params = "w=320&h=240&fit=contain&cbg=black";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 3);
        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("enlarge focal point") {
        auto test_image = fixtures->input_png_with_one_color;
        auto expected_image = fixtures->expected_dir + "/embed-focal.png";
        auto params = "w=320&h=240&fit=contain&a=focal&fpx=0&cbg=black";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 3);
        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("deprecated") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=320&t=letterbox";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 320);
    }
}

TEST_CASE("skip", "[embed]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=261&fit=contain";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 320);
    CHECK(image.height() == 261);
}

TEST_CASE("animated image", "[embed]") {
    SECTION("width only") {
        if (vips_type_find("VipsOperation", true_streaming
                                                ? "gifload_source"
                                                : "gifload_buffer") == 0 ||
            vips_type_find("VipsOperation", pre_8_12
                                                ? "magicksave_buffer"
                                                : "gifsave_target") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&w=400&h=300&fit=contain";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 400);
        CHECK(vips_image_get_page_height(image.get_image()) == 300);
    }

    SECTION("height only") {
        if (vips_type_find("VipsOperation", true_streaming
                                                ? "gifload_source"
                                                : "gifload_buffer") == 0 ||
            vips_type_find("VipsOperation", pre_8_12
                                                ? "magicksave_buffer"
                                                : "gifsave_target") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&w=300&h=400&fit=contain";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 300);
        CHECK(vips_image_get_page_height(image.get_image()) == 400);
    }
}
