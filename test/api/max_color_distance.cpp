#include "max_color_distance.h"

bool MaxColorDistance::match(const VImage &actual) const {
    // Ensure same number of channels
    if (actual.bands() != expected_image_.bands()) {
        throw std::runtime_error("Mismatched bands");
    }

    // Ensure same dimensions
    if (actual.width() != expected_image_.width() ||
        actual.height() != expected_image_.height()) {
        throw std::runtime_error("Mismatched dimensions");
    }

    auto image1 = actual;
    auto image2 = expected_image_;

    // Premultiply and remove alpha
    if (image1.has_alpha()) {
        image1 = image1.premultiply().extract_band(
            1, VImage::option()->set("n", image1.bands() - 1));
    }
    if (image2.has_alpha()) {
        image2 = image2.premultiply().extract_band(
            1, VImage::option()->set("n", image2.bands() - 1));
    }

    // Calculate colour distance
    distance_ = image1.dE00(image2).max();

    return distance_ < accepted_distance_;
}

std::string MaxColorDistance::describe() const {
    std::ostringstream ss;
    ss << "actual image color distance " << distance_
       << "  is less than the expected maximum color distance "
       << accepted_distance_;
    return ss.str();
}
