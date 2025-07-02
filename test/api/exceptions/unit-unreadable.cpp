#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"

using Catch::Matchers::ContainsSubstring;

TEST_CASE("unreadable image", "[unreadable]") {
    SECTION("buffer") {
        if (vips_type_find("VipsOperation", "gifload_buffer") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_buffer = "GIF89a";
        Status status = process_buffer(test_buffer);

        CHECK(!status.ok());
        CHECK(status.code() ==
              static_cast<int>(Status::Code::ImageNotReadable));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(), ContainsSubstring("Image not readable"));
    }
}
