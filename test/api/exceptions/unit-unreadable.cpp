#include <catch2/catch.hpp>

#include "../base.h"

using Catch::Matchers::Contains;

TEST_CASE("unreadable image", "[unreadable]") {
    SECTION("buffer") {
        if (vips_type_find("VipsOperation", pre_8_12 ? "gifload_buffer"
                                                     : "gifload_source") == 0) {
            SUCCEED("no gif support, skipping test");
            return;
        }

        auto test_buffer = "GIF89a";
        Status status = process_buffer(test_buffer);

        CHECK(!status.ok());
        CHECK(status.code() ==
              static_cast<int>(Status::Code::ImageNotReadable));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(), Contains("Image not readable"));
    }
    SECTION("source") {
        // TODO: This test can be moved to unit-invalid when libvips >= 8.12
        class UnreadableSource : public SourceInterface {
            int64_t read(void * /* unsused */, size_t /* unsused */) override {
                return -1;
            }

            int64_t seek(int64_t /* unsused */, int /* unsused */) override {
                return -1;
            }
        };

        Status status = process(
            std::unique_ptr<SourceInterface>(new UnreadableSource()), nullptr);

        CHECK(!status.ok());
        CHECK(status.code() ==
              static_cast<int>(pre_8_12 ? Status::Code::ImageNotReadable
                                        : Status::Code::InvalidImage));
        CHECK(status.error_cause() == Status::ErrorCause::Application);
        CHECK_THAT(status.message(),
                   Contains(pre_8_12 ? "Image not readable"
                                     : "Invalid or unsupported image format"));
    }
}
