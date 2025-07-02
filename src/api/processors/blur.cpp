#include "blur.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

VImage Blur::process(const VImage &image) const {
    // Sigma of gaussian
    auto sigma = query_->get<float>("blur", 0.0F);

    // Should we process the image?
    if (sigma == 0.0F) {
        return image;
    }

    // Sigma needs to be in the range of 0.3 - 1000
    if (sigma < 0.3 || sigma > 1000) {
        // Defaulting to fast, mild blur
        sigma = -1.0F;
    }

    if (sigma == -1.0F) {
        // Fast, mild blur - averages neighbouring pixels
        // clang-format off
        auto blur = VImage::new_matrixv(3, 3,
                                        1.0, 1.0, 1.0,
                                        1.0, 1.0, 1.0,
                                        1.0, 1.0, 1.0);
        // clang-format on
        blur.set("scale", 9.0);
        return image.conv(blur);
    }

    // Slower, accurate Gaussian blur
    return image.gaussblur(sigma);
}

}  // namespace weserv::api::processors
