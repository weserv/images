#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("unsupported saver", "[unsupported]") {
    SECTION("json") {
        auto test_image = fixtures->input_jpg;
        auto params = "output=json";
        auto config = Config();
        config.savers = static_cast<uintptr_t>(Output::All & ~Output::Json);

        std::string out_buf;
        Status status = process_file(test_image, &out_buf, params, config);

        CHECK(!status.ok());
        CHECK(status.code() ==
              static_cast<int>(Status::Code::UnsupportedSaver));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(), Contains("Saving to json is disabled."));
        CHECK(out_buf.empty());

        status = process_file(test_image, &out_buf, "", config);

        CHECK(status.ok());
        CHECK(status.code() == 200);
        CHECK(!out_buf.empty());
    }
}
