#include "processors/tint.h"

namespace weserv {
namespace api {
namespace processors {

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

#if !VIPS_VERSION_AT_LEAST(8, 9, 0)
    // Need to tag generic RGB interpretation as sRGB before libvips 8.9.0. See:
    // https://github.com/libvips/libvips/commit/e48f45187b350344ca90add63b4602f1d1f5a0a0
    if (type_before_tint == VIPS_INTERPRETATION_RGB) {
        type_before_tint = VIPS_INTERPRETATION_sRGB;
    }
#endif

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

}  // namespace processors
}  // namespace api
}  // namespace weserv
