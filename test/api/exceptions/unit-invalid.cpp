#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("invalid image", "[invalid]") {
    SECTION("buffer") {
        auto test_buffer = "<!DOCTYPE html>";
        Status status = process_buffer(test_buffer);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::InvalidImage));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Invalid or unsupported image format"));
    }
    SECTION("file") {
        auto test_file = fixtures->dir + "/doesnotexist.jpg";
        Status status = process_file(test_file, test_file, "");

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::InvalidImage));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Invalid or unsupported image format"));
    }
    SECTION("source") {
        class InvalidSource : public SourceInterface {
            int64_t read(void *data, size_t length) override {
                int64_t available = std::min(length, buffer_.size() - read_pos_);
                if (available <= 0) {
                    return 0;
                }

                buffer_.copy(reinterpret_cast<char *>(data), available,
                             read_pos_);
                read_pos_ += available;
                return available;
            }

            int64_t seek(int64_t /* unsused */, int /* unsused */) override {
                return -1;
            }

         private:
            std::string buffer_{"<!DOCTYPE html>"};
            int64_t read_pos_{0};
        };

        Status status = process(
            std::unique_ptr<SourceInterface>(new InvalidSource()), nullptr);

        CHECK(!status.ok());
        CHECK(status.code() == static_cast<int>(Status::Code::InvalidImage));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains("Invalid or unsupported image format"));
    }
}
