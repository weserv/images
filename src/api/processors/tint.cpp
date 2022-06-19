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

    std::vector<double> lab = tint.to_lab();

    // Get original colorspace
    VipsInterpretation type_before_tint = image.interpretation();

    // Extract luminance
    auto luminance = image.colourspace(VIPS_INTERPRETATION_LAB)[0];

    // Create the tinted version by combining the L from the original and the
    // chroma from the tint
    std::vector<double> chroma{lab[1], lab[2]};

    auto tinted = luminance.bandjoin(chroma)
                      .copy(VImage::option()->set("interpretation",
                                                  VIPS_INTERPRETATION_LAB))
                      .colourspace(type_before_tint);

    // Attach original alpha channel, if any
    if (image.has_alpha()) {
        // Extract original alpha channel
        auto alpha = image[image.bands() - 1];

        // Join alpha channel to normalized image
        tinted = tinted.bandjoin(alpha);
    }

    return tinted;
}

}  // namespace weserv::api::processors
