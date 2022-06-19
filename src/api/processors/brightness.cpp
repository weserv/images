#include "brightness.h"

namespace weserv::api::processors {

VImage Brightness::process(const VImage &image) const {
    auto bri = query_->get_if<int>(
        "bri",
        [](int b) {
            // Brightness needs to be in the range of
            // -100 - 100
            return b >= -100 && b <= 100;
        },
        0);

    // Should we process the image?
    if (bri == 0) {
        return image;
    }

    // Map brightness from -100/100 to -255/255 range
    double brightness = bri * 2.55;

    // Edit the brightness
    if (image.has_alpha()) {
        // Separate alpha channel
        auto image_without_alpha = image.extract_band(
            0, VImage::option()->set("n", image.bands() - 1));
        auto alpha = image[image.bands() - 1];
        return image_without_alpha.linear(1, brightness).bandjoin(alpha);
    }

    return image.linear(1, brightness);

    /*VipsInterpretation old_interpretation = image.interpretation();
    auto lch = image.colourspace(VIPS_INTERPRETATION_LCH);

    // Edit the brightness
    image = lch.linear(1, {brightness, 1, 1}).colourspace(old_interpretation);*/
}

}  // namespace weserv::api::processors
