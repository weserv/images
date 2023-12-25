#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("process timeout", "[timeout]") {
    SECTION("image") {
        auto test_image = fixtures->input_jpg;
        auto params = "blur=300";
        auto config = Config();
        config.process_timeout = 1;

        std::string out_buf;
        Status status = process_file(test_image, &out_buf, params, config);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::LibvipsError));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Maximum image processing time of 1 second exceeded"));
        CHECK(out_buf.empty());
    }
}
