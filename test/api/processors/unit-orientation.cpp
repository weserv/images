#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("orientation", "[orientation]") {
    SECTION("flip") {
        // Trimming before flipping avoids an extra memory copy
        auto test_image = fixtures->input_jpg_overlay_layer_2;
        auto params = "trim=10&flip";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 727);
        CHECK(image.height() == 727);
    }

    SECTION("flop") {
        auto test_image = fixtures->input_jpg_320x240;
        auto params = "flop";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
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

    SECTION("by 270 degrees, rectangular output, fit=cover") {
        auto test_image = fixtures->input_jpg_with_landscape_exif_8;
        auto params = "w=320&h=240&fit=cover";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
    }

    SECTION("by 270 degrees, rectangular output, fit=fill") {
        auto test_image = fixtures->input_jpg_with_landscape_exif_8;
        auto params = "w=320&h=240&fit=fill";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
    }

    SECTION("by 270 degrees, rectangular output, fit=contain") {
        auto test_image = fixtures->input_jpg_with_landscape_exif_8;
        auto params = "w=320&h=240&fit=contain";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
    }
}
