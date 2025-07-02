#include "sharpen.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

VImage Sharpen::process(const VImage &image) const {
    // Sigma of gaussian
    auto sigma = query_->get<float>("sharp", 0.0F);

    // Should we process the image?
    if (sigma == 0.0F) {
        return image;
    }

    // Sigma needs to be in range of 0.000001 - 10, see:
    // https://github.com/libvips/libvips/pull/3270
    if (sigma < 0.000001 || sigma > 10) {
        // Defaulting to fast, mild sharpen
        sigma = -1.0F;
    }

    if (sigma == -1.0F) {
        // Fast, mild sharpen
        // clang-format off
        auto sharpen = VImage::new_matrixv(3, 3,
                                           -1.0, -1.0, -1.0,
                                           -1.0, 32.0, -1.0,
                                           -1.0, -1.0, -1.0);
        // clang-format on
        sharpen.set("scale", 24.0);
        return image.conv(sharpen);
    }

    // Slope for flat areas
    auto flat = query_->get_if<float>(
        "sharpf",
        [](float f) {
            // Slope for flat areas needs to
            // be in range of 0 - 1000000
            return f >= 0 && f <= 1000000;
        },
        1.0F);

    // Slope for jaggy areas
    auto jagged = query_->get_if<float>(
        "sharpj",
        [](float j) {
            // Slope for jaggy areas needs to
            // be in range of 0 - 1000000
            return j >= 0 && j <= 1000000;
        },
        2.0F);

    // Slow, accurate sharpen in LAB colour space,
    // with control over flat vs jagged areas
    return image.sharpen(VImage::option()
                             ->set("sigma", sigma)
                             ->set("m1", flat)
                             ->set("m2", jagged));
}

}  // namespace weserv::api::processors
