#include <catch2/catch_test_macros.hpp>

#include "../base.h"
#include "../max_color_distance.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("modulate", "[modulate]") {
    SECTION("hue-rotate") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/modulate-hue-120.jpg";
        auto params = "w=320&h=240&fit=cover&hue=-240";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_max_color_distance(expected_image, 25));
    }

    SECTION("brighten") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/modulate-brightness-2.jpg";
        auto params = "w=320&h=240&fit=cover&mod=2";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_max_color_distance(expected_image, 25));
    }

    SECTION("unbrighten") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/modulate-brightness-0-5.jpg";
        auto params = "w=320&h=240&fit=cover&mod=0.5";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_max_color_distance(expected_image, 25));
    }

    SECTION("desaturate") {
        auto test_image = fixtures->input_jpg;
        auto expected_image =
            fixtures->expected_dir + "/modulate-saturation-0.5.jpg";
        auto params = "w=320&h=240&fit=cover&sat=0.5";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_max_color_distance(expected_image, 25));
    }

    SECTION("modulate all channels") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/modulate-all.jpg";
        auto params = "w=320&h=240&fit=cover&mod=2,0.5,180";

        VImage image = process_file<VImage>(test_image, params);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_max_color_distance(expected_image, 25));
    }

    SECTION("hue-rotate") {
        auto test_image = fixtures->input_svg_test_pattern;

        std::vector<int> angles{30,  60,  90,  120, 150, 180,
                                210, 240, 270, 300, 330, 360};

        for (const auto &angle : angles) {
            std::string angle_str = std::to_string(angle);

            VImage image = process_file<VImage>(test_image, "hue=" + angle_str);

            auto expected_image = fixtures->expected_dir +
                                  "/modulate-hue-angle-" + angle_str + ".png";

            CHECK_THAT(image, is_max_color_distance(expected_image, 25));
        }
    }

    SECTION("invalid") {
        auto test_image = fixtures->input_jpg;
        auto params = "mod=-1,-1,-1,-1";

        VImage image = process_file<VImage>(test_image, params);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
