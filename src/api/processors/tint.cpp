#include "tint.h"

#include <vector>

namespace weserv::api::processors {

using parsers::Color;

VImage Tint::process(const VImage &image) const {
    auto tint = query_->get<Color>("tint", Color::DEFAULT);

    // Don't process the image if the tint is completely transparent
    if (tint.is_transparent()) {
        return image;
    }

    std::vector<double> tint_lab = tint.to_lab();

    // LAB identity function
    auto identity_lab =
        VImage::identity(VImage::option()->set("bands", 3))
            .colourspace(VIPS_INTERPRETATION_LAB,
                         VImage::option()->set("source_space",
                                               VIPS_INTERPRETATION_sRGB));

    // Scale luminance range, 0.0 to 1.0
    auto l = identity_lab[0] / 100;
    // Weighting functions
    auto weight_L = 1.0 - 4.0 * ((l - 0.5) * (l - 0.5));
    auto weight_AB =
        (weight_L * tint_lab).extract_band(1, VImage::option()->set("n", 2));
    identity_lab = identity_lab[0].bandjoin(weight_AB);

    // Convert lookup table to sRGB
    auto lut = identity_lab.colourspace(
        VIPS_INTERPRETATION_sRGB,
        VImage::option()->set("source_space", VIPS_INTERPRETATION_LAB));

    // Get original colorspace
    VipsInterpretation type_before_tint = image.interpretation();

    // Apply lookup table
    if (image.has_alpha()) {
        // Separate alpha channel
        auto image_without_alpha = image.extract_band(
            0, VImage::option()->set("n", image.bands() - 1));
        auto alpha = image[image.bands() - 1];
        return image_without_alpha.colourspace(VIPS_INTERPRETATION_B_W)
            .maplut(lut)
            .colourspace(type_before_tint)
            .bandjoin(alpha);
    }

    return image.colourspace(VIPS_INTERPRETATION_B_W)
        .maplut(lut)
        .colourspace(type_before_tint);
}

}  // namespace weserv::api::processors
