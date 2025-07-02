#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using Catch::Matchers::Equals;
using vips::VImage;

TEST_CASE("crop positions", "[alignment]") {
    struct CropPosition {
        int width;
        int height;
        std::string position;
        std::string fixture;
    };

    SECTION("as string") {
        // clang-format off
        std::vector<CropPosition> crop_positions{
            // Top left
            {320, 80,  "top-left",     "/position-top.jpg"},
            // Top left
            {80,  320, "top-left",     "/position-left.jpg"},
            // Top
            {320, 80,  "top",          "/position-top.jpg"},
            // Top right
            {320, 80,  "top-right",    "/position-top.jpg"},
            // Top right
            {80,  320, "top-right",    "/position-right.jpg"},
            // Left
            {80,  320, "left",         "/position-left.jpg"},
            // Center
            {320, 80,  "center",       "/position-center.jpg"},
            // Centre
            {80,  320, "center",       "/position-centre.jpg"},
            // Default (centre)
            {80,  320, "",             "/position-centre.jpg"},
            // Right
            {80,  320, "right",        "/position-right.jpg"},
            // Bottom left
            {320, 80,  "bottom-left",  "/position-bottom.jpg"},
            // Bottom left
            {80,  320, "bottom-left",  "/position-left.jpg"},
            // Bottom
            {320, 80,  "bottom",       "/position-bottom.jpg"},
            // Bottom right
            {320, 80,  "bottom-right", "/position-bottom.jpg"},
            // Bottom right
            {80,  320, "bottom-right", "/position-right.jpg"},
            // Deprecated parameters
            // Top
            {320, 80,  "t", "/position-top.jpg"},
            // Left
            {80,  320, "l", "/position-left.jpg"},
            // Right
            {80,  320, "r", "/position-right.jpg"},
            // Bottom
            {320, 80,  "b", "/position-bottom.jpg"},
            // End of deprecated parameters
        };
        // clang-format on

        for (auto const &crop : crop_positions) {
            auto test_image = fixtures->input_jpg;
            auto expected_image = fixtures->expected_dir + crop.fixture;
            auto params = "w=" + std::to_string(crop.width) +
                          "&h=" + std::to_string(crop.height) +
                          "&fit=cover&a=" + crop.position;

            VImage image = process_file<VImage>(test_image, params);

            CHECK(image.width() == crop.width);
            CHECK(image.height() == crop.height);

            CHECK_THAT(image, is_similar_image(expected_image));
        }
    }

    SECTION("as focal point") {
        // clang-format off
        std::vector<CropPosition> crop_positions{
            // Top left
            {320, 80,  "focal&fpx=0&fpy=0",     "/position-top.jpg"},
            // Top left
            {80,  320, "focal&fpx=0&fpy=0",     "/position-left.jpg"},
            // Top
            {320, 80,  "focal&fpx=0.5&fpy=0",   "/position-top.jpg"},
            // Top right
            {320, 80,  "focal&fpx=1&fpy=0",     "/position-top.jpg"},
            // Top right
            {80,  320, "focal&fpx=1&fpy=0",     "/position-right.jpg"},
            // Left
            {80,  320, "focal&fpx=0&fpy=0.5",   "/position-left.jpg"},
            // Center
            {320, 80,  "focal&fpx=0.5&fpy=0.5", "/position-center.jpg"},
            // Centre
            {80,  320, "focal&fpx=0.5&fpy=0.5", "/position-centre.jpg"},
            // Default (centre)
            {80,  320, "focal",                 "/position-centre.jpg"},
            // Right
            {80,  320, "focal&fpx=1&fpy=0.5",   "/position-right.jpg"},
            // Bottom left
            {320, 80,  "focal&fpx=0&fpy=1",     "/position-bottom.jpg"},
            // Bottom left
            {80,  320, "focal&fpx=0&fpy=1",     "/position-left.jpg"},
            // Bottom
            {320, 80,  "focal&fpx=0.5&fpy=1",   "/position-bottom.jpg"},
            // Bottom right
            {320, 80,  "focal&fpx=1&fpy=1",     "/position-bottom.jpg"},
            // Bottom right
            {80,  320, "focal&fpx=1&fpy=1",     "/position-right.jpg"},
            // Deprecated parameters
            // Top
            {320, 80,  "focal-50-0",   "/position-top.jpg"},
            // Left
            {80,  320, "focal-0-50",   "/position-left.jpg"},
            // Right
            {80,  320, "focal-100-50", "/position-right.jpg"},
            // Bottom
            {320, 80,  "focal-50-100", "/position-bottom.jpg"},
            // Top
            {320, 80,  "crop-50-0",    "/position-top.jpg"},
            // Left
            {80,  320, "crop-0-50",    "/position-left.jpg"},
            // Right
            {80,  320, "crop-100-50",  "/position-right.jpg"},
            // Bottom
            {320, 80,  "crop-50-100",  "/position-bottom.jpg"},
            // End of deprecated parameters
        };
        // clang-format on

        for (auto const &crop : crop_positions) {
            auto test_image = fixtures->input_jpg;
            auto expected_image = fixtures->expected_dir + crop.fixture;
            auto params = "w=" + std::to_string(crop.width) +
                          "&h=" + std::to_string(crop.height) +
                          "&fit=cover&a=" + crop.position;

            VImage image = process_file<VImage>(test_image, params);

            CHECK(image.width() == crop.width);
            CHECK(image.height() == crop.height);

            CHECK_THAT(image, is_similar_image(expected_image));
        }
    }
}

TEST_CASE("entropy crop", "[alignment]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/crop-strategy-entropy.jpg";
        auto params = "w=80&h=320&fit=cover&a=entropy";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 3);
        CHECK(image.width() == 80);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png") {
        auto test_image = fixtures->input_png_with_transparency;
        auto expected_image =
            fixtures->expected_dir + "/crop-strategy-entropy.png";
        auto params = "w=320&h=80&fit=cover&a=entropy";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.bands() == 4);
        CHECK(image.width() == 320);
        CHECK(image.height() == 80);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("attention crop", "[alignment]") {
    SECTION("jpeg") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/crop-strategy-attention.jpg";
        auto params = "w=80&h=320&fit=cover&a=attention";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.bands() == 3);
        CHECK(image.width() == 80);
        CHECK(image.height() == 320);
        CHECK(!image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png") {
        auto test_image = fixtures->input_png_embed;
        auto expected_image =
            fixtures->expected_dir + "/crop-strategy-attention.png";
        auto params = "w=200&h=200&fit=cover&a=attention";

        VImage image = process_file<VImage>(test_image, params);

        CHECK_THAT(image.get_string("vips-loader"), Equals("pngload_buffer"));

        CHECK(image.bands() == 4);
        CHECK(image.width() == 200);
        CHECK(image.height() == 200);
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("animated image", "[alignment]") {
    if (vips_type_find("VipsOperation", "gifload_buffer") == 0 ||
        vips_type_find("VipsOperation", "gifsave_buffer") == 0) {
        SUCCEED("no gif support, skipping test");
        return;
    }

    auto test_image = fixtures->input_gif_animated;
    auto params = "n=-1&w=300&h=300&fit=cover";

    VImage image = process_file<VImage>(test_image, params);

    CHECK(image.width() == 300);
    CHECK(vips_image_get_page_height(image.get_image()) == 300);
}
