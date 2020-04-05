#include "processors/saturate.h"

namespace weserv {
namespace api {
namespace processors {

VImage Saturate::process(const VImage &image) const {
    auto mult = query_->get_if<float>("sat", [](float m) {
                                                // multiplier needs to be
                                                // in range of 0 - 10000
                                                return m >= 0 && m <= 10000;
                                            },
                                            1.0F);

    // Should we process the image?
    if (mult == 1.0F) {
        return image;
    }

    // Define saturation matrix
    // clang-format off
    std::array<double, 9> saturate = {
        0.213f + 0.787f * mult, 0.715f - 0.715f * mult, 0.072f - 0.072f * mult,
        0.213f - 0.213f * mult, 0.715f + 0.285f * mult, 0.072f - 0.072f * mult,
        0.213f - 0.213f * mult, 0.715f - 0.715f * mult, 0.072f + 0.928f * mult
    };

    return image
                .colourspace(VIPS_INTERPRETATION_sRGB)
                .recomb(image.bands() == 3
                        ? VImage::new_from_memory(saturate.begin(), 9 * sizeof(double), 3,
                                                  3, 1, VIPS_FORMAT_DOUBLE)
                        : VImage::new_matrixv(4, 4,
                                              saturate[0], saturate[1], saturate[2], 0.0,
                                              saturate[3], saturate[4], saturate[5], 0.0,
                                              saturate[6], saturate[7], saturate[8], 0.0,
                                              0.0, 0.0, 0.0, 1.0));
    // clang-format on
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
