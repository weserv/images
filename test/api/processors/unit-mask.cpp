#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("mask", "[mask]") {
    SECTION("circle") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-circle.png";
        auto params = "w=320&h=240&fit=cover&mask=circle";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("circle trim") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-circle-trim.png";
        auto params = "w=320&h=240&fit=cover&mask=circle&mtrim=true";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 240);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("ellipse") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-ellipse.png";
        auto params = "w=320&h=240&fit=cover&mask=ellipse";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("triangle") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-triangle.png";
        auto params = "w=320&h=240&fit=cover&mask=triangle";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("triangle tilted upside down") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-triangle-180.png";
        auto params = "w=320&h=240&fit=cover&mask=triangle-180";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("pentagon") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-pentagon.png";
        auto params = "w=320&h=240&fit=cover&mask=pentagon";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("pentagon tilted upside down") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-pentagon-180.png";
        auto params = "w=320&h=240&fit=cover&mask=pentagon-180";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("hexagon") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-hexagon.png";
        auto params = "w=320&h=240&fit=cover&mask=hexagon";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("square") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-square.png";
        auto params = "w=320&h=240&fit=cover&mask=square";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("star") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-star.png";
        auto params = "w=320&h=240&fit=cover&mask=star";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("heart") {
        auto test_image = fixtures->input_jpg;
        auto expected_image = fixtures->expected_dir + "/mask-heart.png";
        auto params = "w=320&h=240&fit=cover&mask=heart";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent") {
        auto test_image = fixtures->input_png_overlay_layer_0;
        auto expected_image = fixtures->expected_dir + "/mask-star-trans.png";
        auto params = "w=320&h=240&fit=cover&mask=star";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png transparent background") {
        auto test_image = fixtures->input_png_overlay_layer_0;
        auto expected_image =
            fixtures->expected_dir + "/mask-star-trans-bg.png";
        auto params = "w=320&h=240&fit=cover&mask=star&mbg=red";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("png 2 channels") {
        auto test_image = fixtures->input_png_with_grey_alpha;
        auto expected_image = fixtures->expected_dir + "/mask-2channel.png";
        auto params = "w=320&h=240&fit=cover&mask=triangle-180";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        CHECK(image.width() == 320);
        CHECK(image.height() == 240);

        CHECK_THAT(image, is_similar_image(expected_image));
    }

    SECTION("invalid") {
        auto test_image = fixtures->input_jpg;
        auto params = "mask=none";

        std::string buffer;
        std::tie(buffer, std::ignore) = process_file(test_image, params);

        VImage image = buffer_to_image(buffer);

        // Check if the image is unchanged
        CHECK_THAT(image, is_similar_image(test_image));
    }
}
