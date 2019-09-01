#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("too large image ", "[large]") {
    SECTION("input") {
        if (vips_type_find("VipsOperation", "svgload_buffer") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_image = fixtures->input_svg_giant;
        Status status = check_file_status(test_image);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::ImageTooLarge));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Image is too large for processing"));
    }

    SECTION("output") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=10000000&h=10000000&fit=fill";
        Status status = check_file_status(test_image, params);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::ImageTooLarge));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Requested image dimensions are too large"));
    }
}