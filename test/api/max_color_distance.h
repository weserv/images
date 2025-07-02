#pragma once

#include <catch2/matchers/catch_matchers.hpp>
#include <vips/vips8>

#include <utility>

using vips::VImage;

/**
 * Verifies the maximum color distance using the DE2000 algorithm
 * between two images of the same dimensions and number of channels.
 */
class MaxColorDistance : public Catch::Matchers::MatcherBase<VImage> {
 public:
    explicit MaxColorDistance(VImage expected, const double accepted_distance)
        : expected_image_(std::move(expected)),
          accepted_distance_(accepted_distance) {}

    /**
     * Performs the test for this matcher.
     */
    bool match(const VImage &actual) const override;

    /**
     * Produces a string describing what this matcher does.
     */
    std::string describe() const override;

 private:
    /**
     * Expected image.
     */
    const VImage expected_image_;

    /**
     * Accepted distance threshold. Defaulting to 1.0.
     */
    const double accepted_distance_;

    /**
     * Actual color distance.
     */
    mutable double distance_{0.0};
};

/**
 * The builder functions
 */
inline MaxColorDistance
is_max_color_distance(const std::string &filename,
                      const double accepted_distance = 1.0) {
    auto expected = VImage::new_from_file(
        filename.c_str(),
        VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));
    return MaxColorDistance(expected, accepted_distance);
}

inline MaxColorDistance
is_max_color_distance(const VImage &expected,
                      const double accepted_distance = 1.0) {
    return MaxColorDistance(expected, accepted_distance);
}
