#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("greyscale filter", "[filter]") {
    auto test_image = fixtures->input_jpg;
    auto expected_image = fixtures->expected_dir + "/greyscale.jpg";
    auto params = "w=320&h=240&fit=cover&filt=greyscale";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.bands() == 1);
    CHECK(image.width() == 320);
    CHECK(image.height() == 240);

    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("sepia filter", "[filter]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/sepia.jpg";
        auto params = "w=320&h=240&fit=cover&filt=sepia";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_1;
        auto expected_image = fixtures->expected_dir + "/sepia-trans.png";
        auto params = "w=320&h=240&fit=cover&filt=sepia";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("duotone filter", "[filter]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/duotone.jpg";
        auto params = "w=320&h=240&fit=cover&filt=duotone";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png with grey alpha") {
        auto test_image = fixtures->input_png_with_grey_alpha;
        auto expected_image = fixtures->expected_dir + "/duotone-alpha.png";
        auto params = "w=320&h=240&fit=cover&filt=duotone";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("negate filter", "[filter]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/negate.jpg";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png") {
        auto test_image = fixtures->input_png;
        auto expected_image = fixtures->expected_dir + "/negate.png";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_with_transparency;
        auto expected_image = fixtures->expected_dir + "/negate-trans.png";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png with grey alpha") {
        auto test_image = fixtures->input_png_with_grey_alpha;
        auto expected_image = fixtures->expected_dir + "/negate-alpha.png";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("webp") {
        if (vips_type_find("VipsOperation", "webpload_buffer") == 0 ||
            vips_type_find("VipsOperation", "webpsave_buffer") == 0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp;
        auto expected_image = fixtures->expected_dir + "/negate.webp";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("webp transparent") {
        if (vips_type_find("VipsOperation", "webpload_buffer") == 0 ||
            vips_type_find("VipsOperation", "webpsave_buffer") == 0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp_with_transparency;
        auto expected_image = fixtures->expected_dir + "/negate-trans.webp";
        auto params = "w=320&h=240&fit=cover&filt=negate";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("invalid", "[filter]") {
    auto test_image = fixtures->input_jpg;
    auto params = "filt=none";

    VImage image = process_file<VImage>(test_image, params);

    // Check if the image is unchanged
    CHECK_THAT(image, is_similar_image(test_image));
}
