#include <catch2/catch.hpp>

#include "../base.h"
#include "../similar_image.h"

#include <vips/vips8>

using vips::VImage;

TEST_CASE("saturate grayscale", "[saturate]") {
    // SECTION("jpeg") {
    //     auto test_image = fixtures->input_jpg;
    //     auto expected_image = fixtures->expected_dir + "/saturate-0.jpg";
    //     auto params = "sat=0";

    //     VImage image = process_file<VImage>(test_image, params);

    //     CHECK(image.width() == 320);
    //     CHECK(image.height() == 240);

    //     CHECK_THAT(image, is_similar_image(expected_image));
    // }

    SECTION("png") {
        auto test_image = fixtures->input_png_saturate;
        auto expected_image = fixtures->expected_dir + "/saturate-0.png";
        auto params = "sat=0";

        VImage image = process_file<VImage>(test_image, params);
        
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("saturate undersaturate", "[saturate]") {
    // SECTION("jpeg") {
    //     auto test_image = fixtures->input_jpg;
    //     auto expected_image = fixtures->expected_dir + "/saturate-0.5.jpg";
    //     auto params = "sat=0.5";

    //     VImage image = process_file<VImage>(test_image, params);

    //     CHECK_THAT(image, is_similar_image(expected_image));
    // }

    SECTION("png") {
        auto test_image = fixtures->input_png_saturate;
        auto expected_image = fixtures->expected_dir + "/saturate-0.5.png";
        auto params = "sat=0.5";

        VImage image = process_file<VImage>(test_image, params);
        
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("saturate identity", "[saturate]") {
    // SECTION("jpeg") {
    //     auto test_image = fixtures->input_jpg;
    //     auto expected_image = fixtures->expected_dir + "/saturate-1.jpg";
    //     auto params = "sat=1";

    //     VImage image = process_file<VImage>(test_image, params);

    //     CHECK_THAT(image, is_similar_image(expected_image));
    // }

    SECTION("png") {
        auto test_image = fixtures->input_png_saturate;
        auto expected_image = fixtures->expected_dir + "/saturate-1.png";
        auto params = "sat=1";

        VImage image = process_file<VImage>(test_image, params);
        
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}

TEST_CASE("saturate oversaturate", "[saturate]") {
    // SECTION("jpeg") {
    //     auto test_image = fixtures->input_jpg;
    //     auto expected_image = fixtures->expected_dir + "/saturate-1.5.jpg";
    //     auto params = "sat=1.5";

    //     VImage image = process_file<VImage>(test_image, params);

    //     CHECK_THAT(image, is_similar_image(expected_image));
    // }

    SECTION("png") {
        auto test_image = fixtures->input_png_saturate;
        auto expected_image = fixtures->expected_dir + "/saturate-1.5.png";
        auto params = "sat=1.5";

        VImage image = process_file<VImage>(test_image, params);
        
        CHECK(image.has_alpha());

        CHECK_THAT(image, is_similar_image(expected_image));
    }
}