#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("too large image ", "[large]") {
    SECTION("input") {
        if (vips_type_find("VipsOperation", pre_8_10 ? "svgload_buffer"
                                                     : "svgload_source") == 0) {
            SUCCEED("no svg support, skipping test");
            return;
        }

        auto test_image = fixtures->input_svg_giant;
        Status status = process_file(test_image);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::ImageTooLarge));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Image is too large for processing"));
    }

    SECTION("output") {
        auto test_image = fixtures->input_jpg;
        auto params = "w=10000000&h=10000000&fit=fill";

        std::string out_buf;
        Status status = process_file(test_image, &out_buf, params);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::ImageTooLarge));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Requested image dimensions are too large"));
        CHECK(out_buf.empty());
    }
}