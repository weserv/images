#pragma once

#include <catch2/catch.hpp>
#include <vips/vips8>

using vips::VImage;

/**
 * The similar image matcher class
 */
class SimilarImage : public Catch::MatcherBase<VImage> {
 public:
    explicit SimilarImage(const VImage &expected, const int threshold)
        : expected_hash_(dhash(expected)), threshold_(threshold) {}

    /**
     * Stretch luminance to cover full dynamic range.
     */
    VImage normalize(const VImage &image) const;

    /**
     * Calculate a perceptual hash of an image.
     * Based on the dHash gradient method - see:
     * http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
     */
    std::string dhash(const VImage &image) const;

    /**
     * Calculates dHash hamming distance.
     *
     * See:
     * http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
     */
    int dhash_distance(const std::string &hash1,
                       const std::string &hash2) const;

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
     * Expected hash
     */
    std::string expected_hash_;

    /**
     * Distance threshold. Defaulting to 5 (~7% threshold)
     */
    const int threshold_;

    /**
     * dHash hamming distance.
     */
    mutable int distance_{0};
};

/**
 * The builder functions
 */
inline SimilarImage is_similar_image(const std::string &filename,
                                     const int threshold = 5) {
    auto expected = VImage::new_from_file(
        filename.c_str(),
        VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));
    return SimilarImage(expected, threshold);
}

inline SimilarImage is_similar_image(const VImage &expected,
                                     const int threshold = 5) {
    return SimilarImage(expected, threshold);
}
