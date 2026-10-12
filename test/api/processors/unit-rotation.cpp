#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("rotation", "[rotation]") {
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

    SECTION("by 30 degrees with semi-transparent background") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/rotate-transparent-bg.png";
        auto params = "w=320&ro=30&rbg=80FF0000";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 408);
        CHECK(image.height() == 386);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("by 30 degrees with solid background") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/rotate-solid-bg.jpg";
        auto params = "w=320&ro=30&rbg=FF0000";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 408);
        CHECK(image.height() == 386);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("by 30 degrees with 5-channel tiff") {
        if (vips_type_find("VipsOperation", "tiffload_source") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_target") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff_5_channel;
        auto params = "ro=30&rbg=red&ll";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("tiffload_buffer"));

        CHECK(image.width() == 137);
        CHECK(image.height() == 137);
        CHECK(image.bands() == 5);
    }

    SECTION("by 30 degrees, respecting output") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&fit=cover&ro=30&output=jpg";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 397);
        CHECK(image.height() == 368);
    }

    SECTION("by 30-multiple angle") {
        auto test_image = fixtures->input_jpg_320x240;

        std::vector<int> angles{-3750, -510, -150, 30, 390, 3630};

        for (const auto &angle : angles) {
            VImage image =
                process_file<VImage>(test_image, "ro=" + std::to_string(angle));

            CHECK(image.width() == 397);
            CHECK(image.height() == 368);
        }
    }

    SECTION("by 315 degrees, rectangular output ignoring aspect ratio") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=240&h=240&fit=fill&ro=315";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 339);
        CHECK(image.height() == 339);
    }

    SECTION("by 270 degrees, rectangular output ignoring aspect ratio") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&fit=fill&ro=270";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 240);
        CHECK(image.height() == 320);
    }

    SECTION("by 30 degrees, rectangular output ignoring aspect ratio") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&fit=fill&ro=30";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 397);
        CHECK(image.height() == 368);
    }

    SECTION("by 90 degrees, multi-page image") {
        if (vips_type_find("VipsOperation", "gifload_source") == 0 ||
            vips_type_find("VipsOperation", "gifsave_target") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&ro=90";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 1050);
        CHECK(vips_image_get_page_height(image.get_image()) == 990);
    }

    SECTION("by 30 degrees, skip multi-page image") {
        if (vips_type_find("VipsOperation", "gifload_source") == 0 ||
            vips_type_find("VipsOperation", "gifsave_target") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&ro=30";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 990);

        // Check if page height is unchanged
        CHECK(vips_image_get_page_height(image.get_image()) == 1050);
    }

    SECTION("by 360 degrees, skip") {
        auto test_image = fixtures->input_jpg;
        auto params = "ro=360";

        VImage image = process_file<VImage>(test_image, params);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
