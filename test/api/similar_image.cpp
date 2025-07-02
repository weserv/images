#include "similar_image.h"

#include <array>
#include <iomanip>

VImage SimilarImage::normalize(const VImage &image) const {
    // Get original colorspace
    VipsInterpretation type_before_normalize = image.interpretation();

    // Convert to LAB colorspace
    VImage lab = image.colourspace(VIPS_INTERPRETATION_LAB);

    // Extract luminance
    VImage luminance = lab[0];

    // Find luminance range
    VImage stats = luminance.stats();

    double min = stats(0, 0)[0];
    double max = stats(1, 0)[0];
    if (min != max) {
        // Extract chroma
        VImage chroma = lab.extract_band(1, VImage::option()->set("n", 2));

        // Calculate multiplication factor and addition
        double f = 100.0 / (max - min);
        double a = -(min * f);

        // Scale luminance, join to chroma, convert back to original colorspace
        VImage normalized = luminance.linear(f, a).bandjoin(chroma).colourspace(
            type_before_normalize);

        // Attach original alpha channel, if any
        if (image.has_alpha()) {
            // Extract original alpha channel
            VImage alpha = image[image.bands() - 1];

            // Join alpha channel to normalised image
            return normalized.bandjoin(alpha);
        }

        return normalized;
    }

    return image;
}

std::string SimilarImage::dhash(const VImage &image) const {
    vips::VOption *thumbnail_options = VImage::option()
                                           ->set("height", 8)
                                           ->set("size", VIPS_SIZE_FORCE)
                                           ->set("no_rotate", true)
                                           ->set("linear", false);

    VImage thumbnail =
        image.thumbnail_image(9, thumbnail_options).copy_memory();

    // Apply normalisation - stretch luminance to cover full dynamic range.
    thumbnail = normalize(thumbnail);

    // Flatten it to mid-gray before generating a fingerprint.
    if (image.has_alpha()) {
        std::vector<double> background{128, 128, 128};  // #808080

        thumbnail =
            thumbnail.flatten(VImage::option()->set("background", background));
    }

    thumbnail = thumbnail.colourspace(VIPS_INTERPRETATION_B_W)[0];

    auto *dhash_image =
        static_cast<uint8_t *>(thumbnail.write_to_memory(nullptr));

    // Calculate dHash
    auto hash = 0U;
    auto bit = 1U;

    for (int y = 0; y < 8; ++y) {
        for (int x = 0; x < 8; ++x) {
            auto left = dhash_image[(x * 8) + y];
            auto right = dhash_image[(x * 8) + y + 1];

            // Each hash bit is set based on whether the left pixel is brighter
            // than the right pixel
            if (left > right) {
                hash |= bit;
            }

            // Prepare the next loop
            bit <<= 1U;
        }
    }

    g_free(dhash_image);

    std::ostringstream ss;
    ss << std::hex << std::noshowbase << std::setw(16) << std::setfill('0')
       << hash;

    return ss.str();
}

int hex2int(char ch) {
    if (ch >= '0' && ch <= '9') {
        return ch - '0';
    }
    if (ch >= 'A' && ch <= 'F') {
        return ch - 'A' + 10;
    }
    if (ch >= 'a' && ch <= 'f') {
        return ch - 'a' + 10;
    }
    return -1;
}

int SimilarImage::dhash_distance(const std::string &hash1,
                                 const std::string &hash2) const {
    // Nibble lookup table to reduce computation time, see
    // https://stackoverflow.com/a/25808559
    static const std::array<uint8_t, 16> NIBBLE_LOOKUP = {
        0, 1, 1, 2, 1, 2, 2, 3,
        1, 2, 2, 3, 2, 3, 3, 4
    };

    int res = 0;
    for (size_t i = 0; i < 16; ++i) {
        if (hash1.at(i) != hash2.at(i)) {
            res += NIBBLE_LOOKUP[hex2int(hash1.at(i)) ^ hex2int(hash2.at(i))];
        }
    }

    return res;
}

bool SimilarImage::match(const VImage &actual) const {
    auto actual_hash = dhash(actual);

    distance_ = dhash_distance(expected_hash_, actual_hash);

    return distance_ < threshold_;
}

std::string SimilarImage::describe() const {
    std::ostringstream ss;
    ss << "actual image similarity distance " << distance_
       << "  is less than the threshold " << threshold_;
    return ss.str();
}
