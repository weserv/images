#include <catch2/catch.hpp>

#include "../base.h"

#include <cstdio>
#include <fstream>
#include <vips/vips8>

using Catch::Matchers::Contains;
using Catch::Matchers::Equals;
using Catch::Matchers::StartsWith;
using vips::VImage;

TEST_CASE("output", "[stream]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=300&h=300&fit=cover&output=jpg";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("webp") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "webpload_buffer" : "webpload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "webpsave_buffer" : "webpsave_target") ==
                0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp;
        auto params = "w=300&h=300&fit=cover&output=webp";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("webpload_buffer"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("avif") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "heifload_buffer" : "heifload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "heifsave_buffer" : "heifsave_target") ==
                0) {
            SUCCEED("no avif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_avif;
        auto params = "w=300&h=300&fit=cover&output=avif";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("heifload_buffer"));

        // "heif-compression" metadata added in vips 8.11
        if (vips_version(0) > 8 ||
            vips_version(0) == 8 && vips_version(1) >= 11) {
            CHECK_THAT(image.get_string("heif-compression"), Equals("av1"));
        }

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("tiff") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "tiffload_buffer" : "tiffload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "tiffsave_buffer" : "tiffsave_target") ==
                0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params = "w=300&h=300&fit=cover&output=tiff";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("tiffload_buffer"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("gif") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "gifload_buffer"
                                                     : "gifload_source") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }
        if (vips_type_find("VipsOperation", pre_8_12
                                                ? "magicksave_buffer"
                                                : "magicksave_target") == 0) {
            SUCCEED("no magick support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&w=300&h=300&fit=cover&output=gif";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("gifload_buffer"));

        CHECK(image.width() == 300);
        CHECK(vips_image_get_page_height(image.get_image()) == 318);
    }

    SECTION("png") {
        auto test_image = fixtures->input_png;
        auto params = "w=300&h=300&fit=cover";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("json") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=300&h=300&fit=cover&output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"jpeg")"));
        CHECK_THAT(buffer, Contains(R"("width":300)"));
        CHECK_THAT(buffer, Contains(R"("height":300)"));
    }

    SECTION("origin") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=300&h=300&fit=cover&output=origin";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload_buffer"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }

    SECTION("file") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=300&h=300&fit=cover&output=jpg";

        auto image = process_file<VImage>(test_image, nullptr, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("jpegload"));
        CHECK_THAT(image.filename(), StartsWith("/tmp/image"));

        CHECK(image.width() == 300);
        CHECK(image.height() == 300);
    }
}

TEST_CASE("special page", "[stream]") {
    SECTION("largest") {
        if (vips_type_find("VipsOperation", pre_8_12
                                                ? "magickload_buffer"
                                                : "magickload_source") == 0) {
            SUCCEED("no magick support, skipping test");
            return;
        }

        auto test_image = fixtures->input_ico;
        auto params = "page=-1";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 64);
        CHECK(image.height() == 64);
    }

    SECTION("smallest") {
        if (vips_type_find("VipsOperation", pre_8_12
                                                ? "magickload_buffer"
                                                : "magickload_source") == 0) {
            SUCCEED("no magick support, skipping test");
            return;
        }

        auto test_image = fixtures->input_ico;
        auto params = "page=-2";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.width() == 16);
        CHECK(image.height() == 16);
    }
}

TEST_CASE("quality and compression", "[stream]") {
    SECTION("jpeg quality") {
        auto test_image = fixtures->input_jpg;
        auto params_75 = "w=320&h=240&fit=cover&q=75";
        auto params_85 = "w=320&h=240&fit=cover";
        auto params_95 = "w=320&h=240&fit=cover&q=95";

        std::string buffer_75 =
            process_file<std::string>(test_image, params_75);

        std::string buffer_85 =
            process_file<std::string>(test_image, params_85);

        std::string buffer_95 =
            process_file<std::string>(test_image, params_95);

        CHECK(buffer_75.size() < buffer_85.size());
        CHECK(buffer_85.size() < buffer_95.size());
    }

    SECTION("png level") {
        auto test_image = fixtures->input_png;
        auto params_3 = "w=320&h=240&fit=cover&l=3";
        auto params_6 = "w=320&h=240&fit=cover";
        // auto params_9 = "w=320&h=240&fit=cover&l=9";

        std::string buffer_3 = process_file<std::string>(test_image, params_3);

        std::string buffer_6 = process_file<std::string>(test_image, params_6);

        // std::string buffer_9 = process_file<std::string>(test_image,
        // params_9);

        CHECK(buffer_3.size() < buffer_6.size());
        // CHECK(buffer_6.size() < buffer_9.size());
    }

    SECTION("webp quality") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "webpload_buffer" : "webpload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "webpsave_buffer" : "webpsave_target") ==
                0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp;
        auto params_75 = "w=320&h=240&fit=cover&q=75";
        auto params_85 = "w=320&h=240&fit=cover";
        auto params_95 = "w=320&h=240&fit=cover&q=95";

        std::string buffer_75 =
            process_file<std::string>(test_image, params_75);

        std::string buffer_85 =
            process_file<std::string>(test_image, params_85);

        std::string buffer_95 =
            process_file<std::string>(test_image, params_95);

        CHECK(buffer_75.size() < buffer_85.size());
        CHECK(buffer_85.size() < buffer_95.size());
    }

    SECTION("avif quality") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "heifload_buffer" : "heifload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "heifsave_buffer" : "heifsave_target") ==
                0) {
            SUCCEED("no avif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_avif;
        auto params_75 = "w=320&h=240&fit=cover&q=75";
        auto params_95 = "w=320&h=240&fit=cover&q=95";

        std::string buffer_75 =
            process_file<std::string>(test_image, params_75);

        std::string buffer_95 =
            process_file<std::string>(test_image, params_95);

        CHECK(buffer_75.size() < buffer_95.size());
    }

    SECTION("tiff quality") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "tiffload_buffer" : "tiffload_source") ==
                0 ||
            vips_type_find("VipsOperation",
                           pre_8_12 ? "tiffsave_buffer" : "tiffsave_target") ==
                0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params_75 = "w=320&h=240&fit=cover&q=75";
        auto params_85 = "w=320&h=240&fit=cover";
        auto params_95 = "w=320&h=240&fit=cover&q=95";

        std::string buffer_75 =
            process_file<std::string>(test_image, params_75);

        std::string buffer_85 =
            process_file<std::string>(test_image, params_85);

        std::string buffer_95 =
            process_file<std::string>(test_image, params_95);

        CHECK(buffer_75.size() < buffer_85.size());
        CHECK(buffer_85.size() < buffer_95.size());
    }
}

TEST_CASE("without adaptive filtering generates smaller file", "[stream]") {
    auto test_image = fixtures->input_png;
    auto params_af = "w=320&h=240&fit=cover&af=true";
    auto params_without_af = "w=320&h=240&fit=cover&af=false";

    std::string buffer_af = process_file<std::string>(test_image, params_af);

    std::string buffer_without_af =
        process_file<std::string>(test_image, params_without_af);

    CHECK(buffer_without_af.size() < buffer_af.size());
}

TEST_CASE("gif options", "[stream]") {
    SECTION("loop count") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "gifload_buffer"
                                                     : "gifload_source") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&loop=1&output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"gif")"));
        CHECK_THAT(buffer, Contains(R"("pages":8)"));
        CHECK_THAT(buffer, Contains(R"("pageHeight":1050)"));
        CHECK_THAT(buffer, Contains(R"("loop":1)"));
    }

    SECTION("frame delay") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "gifload_buffer"
                                                     : "gifload_source") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "n=-1&delay=200&output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"gif")"));
        CHECK_THAT(buffer, Contains(R"("pages":8)"));
        CHECK_THAT(buffer, Contains(R"("pageHeight":1050)"));
        CHECK_THAT(buffer, Contains(R"("delay":[200)"));
    }

    SECTION("page height") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "gifload_buffer"
                                                     : "gifload_source") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_gif_animated;
        auto params = "fit=contain&w=990&h=2100&output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"gif")"));
        CHECK_THAT(buffer, Contains(R"("pages":8)"));
        CHECK_THAT(buffer, Contains(R"("pageHeight":2100)"));
    }
}

TEST_CASE("metadata", "[stream]") {
    SECTION("jpeg cymk") {
        auto test_image = fixtures->input_jpg_with_cmyk_profile;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"jpeg")"));
        CHECK_THAT(buffer, Contains(R"("chromaSubsampling":"4:4:4:4")"));
        CHECK_THAT(buffer, Contains(R"("isProgressive":false)"));
        CHECK_THAT(buffer, Contains(R"("density":180)"));
    }

    SECTION("png 8 bit paletted") {
        auto test_image = fixtures->input_png_8bit_palette;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"png")"));
        CHECK_THAT(buffer, Contains(R"("paletteBitDepth":8)"));
    }

    SECTION("webp") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "webpload_buffer" : "webpload_source") ==
            0) {
            SUCCEED("no webp support, skipping test");
            return;
        }

        auto test_image = fixtures->input_webp;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"webp")"));
    }

    SECTION("avif") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "heifload_buffer" : "heifload_source") ==
            0) {
            SUCCEED("no avif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_avif;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"heif")"));
    }

    SECTION("tiff") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "tiffload_buffer" : "tiffload_source") ==
            0) {
            SUCCEED("no tiff support, skipping test");
            return;
        }

        auto test_image = fixtures->input_tiff;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"tiff")"));
    }

    SECTION("svg") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "svgload_buffer"
                                                     : "svgload_source") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_image = fixtures->input_svg;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"svg")"));
    }

    SECTION("pdf") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "pdfload_buffer"
                                                     : "pdfload_source") == 0) {
            SUCCEED("no pdf support, skipping test");
            return;
        }

        auto test_image = fixtures->input_pdf;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"pdf")"));
    }

    SECTION("heic") {
        if (vips_type_find("VipsOperation",
                           pre_8_12 ? "heifload_buffer" : "heifload_source") ==
            0) {
            SUCCEED("no heif support, skipping test");
            return;
        }

        auto test_image = fixtures->input_heic;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"heif")"));
        CHECK_THAT(buffer, Contains(R"("pagePrimary":0)"));
    }

    SECTION("magick") {
        if (vips_type_find("VipsOperation", pre_8_12
                                                ? "magickload_buffer"
                                                : "magickload_source") == 0) {
            SUCCEED("no magick support, skipping test");
            return;
        }

        auto test_image = fixtures->input_ico;
        auto params = "output=json";

        std::string buffer = process_file<std::string>(test_image, params);

        CHECK_THAT(buffer, Contains(R"("format":"magick")"));
    }
}
