#include <catch2/catch.hpp>

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
            std::string buffer;
            std::tie(buffer, std::ignore) =
                process_file(test_image, "ro=" + std::to_string(angle));

            VImage image = buffer_to_image(buffer);

            CHECK(image.width() == 240);
            CHECK(image.height() == 320);
        }
    }

    // Rotate by any 180-multiple angle
    SECTION("by 180 multiple angle") {
        auto test_image = fixtures->input_jpg_320x240;

        std::vector<int> angles{-3780, -540, 0, 180, 540, 3780};

        for (const auto &angle : angles) {
            std::string buffer;
            std::tie(buffer, std::ignore) =
                process_file(test_image, "ro=" + std::to_string(angle));

            VImage image = buffer_to_image(buffer);

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

            std::string buffer;
            std::tie(buffer, std::ignore) = process_file(test_image, "w=320");

            VImage image = buffer_to_image(buffer);

            CHECK(image.width() == 320);
            CHECK(image.height() == 240);

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

            std::string buffer;
            std::tie(buffer, std::ignore) = process_file(test_image, "w=320");

            VImage image = buffer_to_image(buffer);

            CHECK(image.width() == 320);
            CHECK(image.height() == 427);

            // Check if the EXIF orientation header is removed
            CHECK(image.get_typeof(VIPS_META_ORIENTATION) == 0);

            auto expected_file =
                "/Portrait_" + std::to_string(i + 1) + "-out.jpg";
            CHECK_THAT(image, is_similar_image(fixtures->expected_dir +
                                               expected_file));
        }
    }
}

TEST_CASE("rotate by 270 degrees, square output ignoring aspect ratio",
          "[thumbnail]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=240&h=240&fit=fill&ro=270";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 240);
    CHECK(image.height() == 240);
}

TEST_CASE("rotate by 270 degrees, rectangular output ignoring aspect ratio",
          "[thumbnail]") {
    auto test_image = fixtures->input_jpg;
    auto params = "w=320&h=240&fit=fill&ro=270";

    std::string buffer;
    std::tie(buffer, std::ignore) = process_file(test_image, params);

    VImage image = buffer_to_image(buffer);

    CHECK(image.width() == 320);
    CHECK(image.height() == 240);
}
