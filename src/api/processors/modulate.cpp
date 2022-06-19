#include "modulate.h"

namespace weserv::api::processors {

VImage Modulate::process(const VImage &image) const {
    auto brightness = query_->get_if<float>(
        /*"bri"*/"mod",
        [](float b) {
            // Brightness needs to be in range of 0 - 10000
            return b >= 0 && b <= 10000;
        },
        1.0F);
    auto saturation = query_->get_if<float>(
        "sat",
        [](float s) {
            // Saturation needs to be in range of 0 - 10000
            return s >= 0 && s <= 10000;
        },
        1.0F);
    auto hue = query_->get<int>("hue", 0);  // Normalized to [0, 360] below

    // Should we process the image?
    if (brightness == 1.0 && saturation == 1.0 && hue == 0) {
        return image;
    }

    // Normalize hue rotation to [0, 360]
    hue %= 360;
    if (hue < 0) {
        hue = 360 + hue;
    }

    // Get original colorspace
    VipsInterpretation type_before_modulate = image.interpretation();

    // Modulate brightness, saturation and hue
    if (image.has_alpha()) {
        // Separate alpha channel
        auto image_without_alpha = image.extract_band(
            0, VImage::option()->set("n", image.bands() - 1));
        auto alpha = image[image.bands() - 1];
        return image_without_alpha.colourspace(VIPS_INTERPRETATION_LCH)
            .linear({brightness, saturation, 1},
                    {0.0, 0.0, static_cast<double>(hue)})
            .colourspace(type_before_modulate)
            .bandjoin(alpha);
    }

    return image.colourspace(VIPS_INTERPRETATION_LCH)
        .linear({brightness, saturation, 1},
                {0.0, 0.0, static_cast<double>(hue)})
        .colourspace(type_before_modulate);
}

}  // namespace weserv::api::processors
