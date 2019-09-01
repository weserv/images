#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("unreadable image", "[unreadable]") {
    if (vips_type_find("VipsOperation", "gifload_buffer") == 0) {
        SUCCEED("no gif support, skipping test");
        return;
    }

    auto test_buffer = "GIF89a";
    Status status = check_buffer_status(test_buffer);

    CHECK(!status.ok());
    CHECK(status.code() == static_cast<int>(Status::Code::ImageNotReadable));
    CHECK(status.error_cause() == Status::ErrorCause::Application);
    CHECK_THAT(status.message(), Contains("Image not readable"));
}
