#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("partial image extract", "[crop]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/extract.jpg";
        auto params = "cx=2&cy=2&cw=20&ch=20";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 20);
        CHECK(image.height() == 20);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png") {
        auto test_image = fixtures->input_png;
        auto expected_image = fixtures->expected_dir + "/extract.png";
        auto params = "cx=200&cy=300&cw=400&ch=200";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 400);
        CHECK(image.height() == 200);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("webp") {
        if (vips_type_find("VipsOperation", "webpload_buffer") == 0 ||
            vips_type_find("VipsOperation", "webpsave_buffer") == 0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp;
        auto expected_image = fixtures->expected_dir + "/extract.webp";
        auto params = "cx=100&cy=50&cw=125&ch=200";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("webpload_buffer"));

        CHECK(image.width() == 125);
        CHECK(image.height() == 200);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("tiff") {
        if (vips_type_find("VipsOperation", "tiffload_buffer") == 0 ||
            vips_type_find("VipsOperation", "tiffsave_buffer") == 0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto expected_image = fixtures->expected_dir + "/extract.tiff";
        auto params = "cx=34&cy=63&cw=341&ch=529";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("tiffload_buffer"));

        CHECK(image.width() == 341);
        CHECK(image.height() == 529);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("deprecated") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/extract.jpg";
        auto params = "crop=20,20,2,2";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 20);
        CHECK(image.height() == 20);

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("image extract before resize", "[crop]") {
    auto test_image = fixtures->input_jpg;
    auto expected_image = fixtures->expected_dir + "/extract-resize.jpg";
    auto params = "cx=10&cy=10&cw=10&ch=500&w=100&h=100&fit=cover&precrop";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 100);
    CHECK(image.height() == 100);

    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("image resize and extract svg 72 dpi", "[crop]") {
    if (vips_type_find("VipsOperation", "svgload_buffer") == 0) {
        SUCCEED("no svg support, skipping test");
        return;
    }

    auto test_image = fixtures->input_svg;
    auto expected_image = fixtures->expected_dir + "/svg72.png";
    auto params = "w=1024&fit=outside&cx=290&cy=760&cw=40&ch=40";

    VImage image = process_file<VImage>(test_image, params);

    CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

    CHECK(image.width() == 40);
    CHECK(image.height() == 40);

    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("image resize crop and extract", "[crop]") {
    auto test_image = fixtures->input_jpg;
    auto expected_image = fixtures->expected_dir + "/resize-crop-extract.jpg";
    auto params = "w=500&h=500&fit=cover&a=top&cx=10&cy=10&cw=100&ch=100";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 100);
    CHECK(image.height() == 100);

    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("rotate and extract", "[crop]") {
    auto test_image = fixtures->input_png_with_grey_alpha;
    auto expected_image = fixtures->expected_dir + "/rotate-extract.png";
    auto params = "ro=90&cx=20&cy=10&cw=280&ch=380";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 280);
    CHECK(image.height() == 380);

    CHECK_THAT(image, is_similar_image(expected_image));
}

TEST_CASE("limit to image boundaries", "[crop]") {
    auto test_image = fixtures->input_jpg;
    auto params = "cx=2405&cy=1985&cw=30000&ch=30000";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 320);
    CHECK(image.height() == 240);
}

TEST_CASE("negative", "[crop]") {
    auto test_image = fixtures->input_jpg;

    SECTION("width") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=-10&ch=10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 310);
        CHECK(image.height() == 10);
    }

    SECTION("height") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=10&ch=-10";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 10);
        CHECK(image.height() == 230);
    }
}

TEST_CASE("percentage-based", "[crop]") {
    auto test_image = fixtures->input_jpg;

    SECTION("width") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=50%&ch=100%";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 160);
        CHECK(image.height() == 230);
    }

    SECTION("height") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=100%&ch=50%";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 310);
        CHECK(image.height() == 120);
    }

    SECTION("URL-encoded") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=50%25&ch=100%25";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 160);
        CHECK(image.height() == 230);
    }

    SECTION("invalid") {
        auto params = "w=320&h=240&fit=cover&cx=10&cy=10&cw=200%&ch=";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 310);
        CHECK(image.height() == 230);
    }
}

TEST_CASE("bad extract area", "[crop]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=240&fit=cover&cx=3000&cy=10&cw=10&ch=10";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 10);
    CHECK(image.height() == 10);
}

TEST_CASE("animated image", "[crop]") {
    if (vips_type_find("VipsOperation", "gifload_buffer") == 0 ||
        vips_type_find("VipsOperation", "gifsave_buffer") == 0) {
        SUCCEED("no gif support, skipping test");
        return;
    }

    auto test_image = fixtures->input_gif_animated;
    auto params = "n=-1&cx=240&cw=510";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 510);
    CHECK(vips_image_get_page_height(image.get_image()) == 1050);
}
