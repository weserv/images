#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("orientation", "[orientation]") {
    // Rotate by any 90-multiple angle
    SECTION("by 90 multiple angle") {
        auto test_image = fixtures->input_jpg_320x240;

        std::vector<int> angles{-3690, -450, -90, 90, 450, 3690};

        for (const auto &angle : angles) {
            VImage image =
                process_file<VImage>(test_image, "ro=" + std::to_string(angle));

            CHECK(image.width() == 240);
            CHECK(image.height() == 320);
        }
    }

    // Rotate by any 180-multiple angle
    SECTION("by 180 multiple angle") {
        auto test_image = fixtures->input_jpg_320x240;

        std::vector<int> angles{-3780, -540, 0, 180, 540, 3780};

        for (const auto &angle : angles) {
            VImage image =
                process_file<VImage>(test_image, "ro=" + std::to_string(angle));

            CHECK(image.width() == 320);
            CHECK(image.height() == 240);
        }
    }
}

TEST_CASE("auto rotate", "[orientation]") {
    SECTION("landscape") {
        std::vector<std::string> landscape_fixtures{
            fixtures->input_jpg_with_landscape_exif_1,
            fixtures->input_jpg_with_landscape_exif_2,
            fixtures->input_jpg_with_landscape_exif_3,
            fixtures->input_jpg_with_landscape_exif_4,
            fixtures->input_jpg_with_landscape_exif_5,
            fixtures->input_jpg_with_landscape_exif_6,
            fixtures->input_jpg_with_landscape_exif_7,
            fixtures->input_jpg_with_landscape_exif_8};

        for (size_t i = 0; i != landscape_fixtures.size(); i++) {
            auto test_image = landscape_fixtures[i];

            VImage image = process_file<VImage>(test_image, "w=320");

            CHECK(image.width() == 320);
            CHECK(image.height() == 213);

            // Check if the EXIF orientation header is removed
            CHECK(image.get_typeof(VIPS_META_ORIENTATION) == 0);

            auto expected_file =
                "/Landscape_" + std::to_string(i + 1) + "-out.jpg";
            CHECK_THAT(image, is_similar_image(fixtures->expected_dir +
                                               expected_file));
        }
    }

    SECTION("portrait") {
        std::vector<std::string> portrait_fixtures{
            fixtures->input_jpg_with_portrait_exif_1,
            fixtures->input_jpg_with_portrait_exif_2,
            fixtures->input_jpg_with_portrait_exif_3,
            fixtures->input_jpg_with_portrait_exif_4,
            fixtures->input_jpg_with_portrait_exif_5,
            fixtures->input_jpg_with_portrait_exif_6,
            fixtures->input_jpg_with_portrait_exif_7,
            fixtures->input_jpg_with_portrait_exif_8};

        for (size_t i = 0; i != portrait_fixtures.size(); i++) {
            auto test_image = portrait_fixtures[i];

            VImage image = process_file<VImage>(test_image, "w=320");

            CHECK(image.width() == 320);
            CHECK(image.height() == 480);

            // Check if the EXIF orientation header is removed
            CHECK(image.get_typeof(VIPS_META_ORIENTATION) == 0);

            auto expected_file =
                "/Portrait_" + std::to_string(i + 1) + "-out.jpg";
            CHECK_THAT(image, is_similar_image(fixtures->expected_dir +
                                               expected_file));
        }
    }
}

TEST_CASE(
    "auto rotate by 270 degrees, rectangular output ignoring aspect ratio",
    "[orientation]") {
    auto test_image = fixtures->input_jpg_with_landscape_exif_8;
    auto params = "w=320&h=240&fit=fill";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 320);
    CHECK(image.height() == 240);
}

TEST_CASE("rectangular output ignoring aspect ratio, rotate by 270 degrees",
          "[orientation]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=240&fit=fill&ro=270";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 240);
    CHECK(image.height() == 320);
}

TEST_CASE("rotate a multi-page image with a non-straight angle",
          "[orientation]") {
    if (vips_type_find("VipsOperation", "gifload_buffer") == 0 ||
        vips_type_find("VipsOperation", "gifsave_buffer") == 0) {
        SUCCEED("no gif support, skipping test");
        return;
    }

    auto test_image = fixtures->input_gif_animated;
    auto params = "n=-1&ro=90";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 1050);
    CHECK(vips_image_get_page_height(image.get_image()) == 990);
}

TEST_CASE("random access flip", "[orientation]") {
    auto test_image = fixtures->input_jpg_overlay_layer_2;
    auto params = "trim=10&flip";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 727);
    CHECK(image.height() == 727);
}
