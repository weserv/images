#include "gamma.h"

namespace weserv::api::processors {

VImage Gamma::process(const VImage &image) const {
    auto gamma = query_->get<float>("gam", 0.0F);

    // Should we process the image?
    if (gamma == 0.0F) {
        return image;
    }

    // Gamma needs to be in the range of 1.0 - 3.0
    if (gamma < 1.0 || gamma > 3.0) {
        // Set gamma to the default correction (sRGB)
        gamma = 2.2;
    }

    // Edit the gamma
    if (image.has_alpha()) {
        // Separate alpha channel
        auto image_without_alpha = image.extract_band(
            0, VImage::option()->set("n", image.bands() - 1));
        auto alpha = image[image.bands() - 1];
        return image_without_alpha
            .gamma(VImage::option()->set("exponent", 1.0 / gamma))
            .bandjoin(alpha);
    }

    return image.gamma(VImage::option()->set("exponent", 1.0 / gamma));
}

}  // namespace weserv::api::processors
