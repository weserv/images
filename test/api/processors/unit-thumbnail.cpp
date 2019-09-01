#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Contains;
using vips::VImage;

TEST_CASE("inside", "[thumbnail]") {
    SECTION("default") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 294);
        CHECK(image.height() == 240);
        CHECK(!image.has_alpha());
    }

    SECTION("device pixel ratio") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=160&h=120&dpr=2";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 294);
        CHECK(image.height() == 240);
        CHECK(!image.has_alpha());
    }

    SECTION("with enlargement") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&fit=inside&we=false";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 2450);
        CHECK(!image.has_alpha());
    }

    SECTION("deprecated") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&t=fit";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 294);
        CHECK(image.height() == 240);
        CHECK(!image.has_alpha());
    }

    SECTION("deprecated with enlargement") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&t=fitup";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 2450);
        CHECK(!image.has_alpha());
    }
}

// Provide only one dimension, should default to inside
TEST_CASE("fixed", "[thumbnail]") {
    SECTION("width") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&fsol=0";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 261);
        CHECK(!image.has_alpha());
    }

    SECTION("height") {
        auto test_image = fixtures->input_jpg;
        auto params = "h=320&fsol=0";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 392);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }
}

TEST_CASE("invalid height", "[thumbnail]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=100000000&fsol=0";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 320);
    CHECK(image.height() == 261);
    CHECK(!image.has_alpha());
}

TEST_CASE("identity transform", "[thumbnail]") {
    auto test_image = fixtures->input_jpg;

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 2725);
    CHECK(image.height() == 2225);
    CHECK(!image.has_alpha());
}

TEST_CASE("cover", "[thumbnail]") {
    SECTION("normal") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&fit=cover";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(!image.has_alpha());
    }

    // Use the smaller axis in crop mode, we aim to fill the bounding box
    SECTION("smaller axis") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=123&h=100&fit=cover&we=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 123);
        CHECK(image.height() == 100);
        CHECK(!image.has_alpha());
    }

    // Don't use the crop mode when oversampling with `&we=true`
    SECTION("smaller axis down width") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&h=3000&fit=cover&we=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    SECTION("upscale") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 2450);
        CHECK(!image.has_alpha());
    }

    // Do not enlarge when input width is already less than output width
    SECTION("down width") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=2800&fit=cover&we=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    // Do not enlarge when input height is already less than output height
    SECTION("down height") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=2800&fit=cover&we=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    SECTION("deprecated") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=240&t=square";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);
        CHECK(!image.has_alpha());
    }

    SECTION("deprecated without enlargement") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=2800&t=squaredown";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }
}

TEST_CASE("tiff", "[thumbnail]") {
    SECTION("cover") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params = "w=240&h=320&fit=cover";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 240);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    // Width or height considering ratio (portrait)
    SECTION("smaller axis") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params = "w=320&h=320";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 243);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    SECTION("pyramid") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff_pyramid;
        auto expected_image = fixtures->expected_dir + "/tiff-pyramid.tiff";
        auto params = "w=500&h=103";  // page=3

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 500);
        CHECK(image.height() == 103);
        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("pyramid skip shrink-on-load") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff_pyramid;
        auto params = "w=4000&h=828";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 4000);
        CHECK(image.height() == 828);
    }

    SECTION("multi-page skip shrink-on-load") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff_multi_page;
        auto params = "w=600&h=75";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 600);
        CHECK(image.height() == 75);
    }
}

// Width or height considering ratio (landscape)
TEST_CASE("jpg ratio landscape", "[thumbnail]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=320&fsol=0";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 320);
    CHECK(image.height() == 261);
    CHECK(!image.has_alpha());
}

TEST_CASE("fill", "[thumbnail]") {
    // Downscale width and height, ignoring aspect ratio
    SECTION("downscale") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=320&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    // Downscale width, ignoring aspect ratio
    SECTION("downscale width") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    // Downscale height, ignoring aspect ratio
    SECTION("downscale height") {
        auto test_image = fixtures->input_jpg;
        auto params = "h=320&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    // Upscale width and height, ignoring aspect ratio
    SECTION("upscale") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&h=3000&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 3000);
        CHECK(!image.has_alpha());
    }

    // Upscale width, ignoring aspect ratio
    SECTION("upscale width") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    // Upscale height, ignoring aspect ratio
    SECTION("upscale height") {
        auto test_image = fixtures->input_jpg;
        auto params = "h=3000&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 3000);
        CHECK(!image.has_alpha());
    }

    // Downscale width, upscale height, ignoring aspect ratio
    SECTION("downscale width upscale height") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=3000&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 3000);
        CHECK(!image.has_alpha());
    }

    // Upscale width, downscale height, ignoring aspect ratio
    SECTION("upscale width downscale height") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=3000&h=320&fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 3000);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }

    SECTION("identity transform") {
        auto test_image = fixtures->input_jpg;
        auto params = "fit=fill";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2725);
        CHECK(image.height() == 2225);
        CHECK(!image.has_alpha());
    }

    SECTION("deprecated") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=320&h=320&t=absolute";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());
    }
}

TEST_CASE("from", "[thumbnail]") {
    // From CMYK to sRGB
    SECTION("CMYK to sRGB") {
        auto test_image = fixtures->input_jpg_with_cmyk_profile;
        auto params = "w=320";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.interpretation() == VIPS_INTERPRETATION_sRGB);
        CHECK(image.width() == 320);
    }

    // From profile-less CMYK to sRGB
    SECTION("smaller axis") {
        auto test_image = fixtures->input_jpg_with_cmyk_no_profile;
        auto expected_image =
            fixtures->expected_dir + "/colourspace.cmyk-without-profile.jpg";
        auto params = "w=320";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.interpretation() == VIPS_INTERPRETATION_sRGB);
        CHECK(image.width() == 320);
        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("shortest edge is at least 1 pixel", "[thumbnail]") {
    SECTION("height") {
        if (vips_type_find("VipsOperation", "svgload_buffer") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_buffer = R"(<svg width="10" height="2"></svg>)";
        auto params = "w=2";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_buffer(test_buffer, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 2);
        CHECK(image.height() == 1);
    }

    SECTION("width") {
        if (vips_type_find("VipsOperation", "svgload_buffer") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_buffer = R"(<svg width="2" height="10"></svg>)";
        auto params = "h=2";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_buffer(test_buffer, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 1);
        CHECK(image.height() == 2);
    }

    SECTION("shrink-on-load") {
        if (vips_type_find("VipsOperation", "svgload_buffer") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_buffer = R"(<svg width="1" height="10"></svg>)";
        auto params = "h=5";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_buffer(test_buffer, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 1);
        CHECK(image.height() == 5);
    }
}

TEST_CASE("heif", "[thumbnail]") {
    if (vips_type_find("VipsOperation", "heifload_buffer") == 0) {
        SUCCEED("no heic support, skipping test");
        return;
    }

    auto test_image = fixtures->input_heic;
    auto expected_image = fixtures->expected_dir + "/heif-thumbnail.jpg";
    auto params = "w=240&h=160&output=jpg";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 240);
    CHECK(image.height() == 160);
    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("animated webp page", "[thumbnail]") {
    if (vips_type_find("VipsOperation", "webpload_buffer") == 0 ||
        vips_type_find("VipsOperation", "webpsave_buffer") == 0) {
        SUCCEED("no webp support, skipping test");
        return;
    }

    auto test_image = fixtures->input_webp_animated;
    auto expected_image = fixtures->expected_dir + "/individual-page.webp";
    auto params = "w=320&h=339&page=6";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 320);
    CHECK(image.height() == 339);
    CHECK_THAT(image, is_similar_image(expected_image));
}
