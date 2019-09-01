#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("invalid image ", "[invalid]") {
    auto test_buffer = "<!DOCTYPE html>";
    Status status = check_buffer_status(test_buffer);

    CHECK(!status.ok());
    CHECK(status.code() == static_cast<int>(Status::Code::InvalidImage));
    CHECK(status.error_cause() == Status::ErrorCause::Application);
    CHECK_THAT(status.message(),
               Contains("Invalid or unsupported image format"));
}