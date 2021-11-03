#include "sharpen.h"

#include "../utils/utility.h"

namespace weserv {
namespace api {
namespace processors {

VImage Sharpen::process(const VImage &image) const {
    // Should we process the image?
    if (!query_->exists("sharp")) {
        return image;
    }

    // Sigma of gaussian
    auto sigma = query_->get_if<float>(
        "sharp",
        [](float s) {
            // Sigma needs to be in range of
            // 0.000001 - 10000
            return s >= 0.000001 && s <= 10000;
        },
        -1.0F);

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
            // be in range of 0 - 10000
            return f >= 0 && f <= 10000;
        },
        1.0F);

    // Slope for jaggy areas
    auto jagged = query_->get_if<float>(
        "sharpj",
        [](float j) {
            // Slope for jaggy areas needs
            // to be in range of 0 - 10000
            return j >= 0 && j <= 10000;
        },
        2.0F);

    // Slow, accurate sharpen in LAB colour space,
    // with control over flat vs jagged areas
    return (image.get_typeof(VIPS_META_SEQUENTIAL) != 0
                ? utils::line_cache(image, 10)
                : image)
        .sharpen(VImage::option()
                     ->set("sigma", sigma)
                     ->set("m1", flat)
                     ->set("m2", jagged));
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
