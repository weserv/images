#include <catch2/catch_test_macros.hpp>
#include <catch2/matchers/catch_matchers_string.hpp>

#include "../base.h"

using Catch::Matchers::ContainsSubstring;

TEST_CASE("libvips error", "[other]") {
    SECTION("input file") {
        auto test_file = fixtures->dir + "/doesnotexist.jpg";
        std::string buffer;
        Status status = process_file(test_file, &buffer, "");

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::LibvipsError));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   ContainsSubstring("No such file or directory"));
    }
    SECTION("output file") {
        auto test_image = fixtures->input_jpg;
        Status status = process_file(test_image, "", "");

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::LibvipsError));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   ContainsSubstring("No such file or directory"));
    }
}
