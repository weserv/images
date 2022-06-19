#include "filter.h"

#include "../enums.h"

#include <array>
#include <vector>

namespace weserv::api::processors {

using enums::FilterType;
using parsers::Color;

VImage Filter::process(const VImage &image) const {
    auto filter_type = query_->get<FilterType>("filt", FilterType::None);

    // Should we process the image?
    if (filter_type == FilterType::None) {
        return image;
    }

    switch (filter_type) {
        case FilterType::Greyscale:
            // Perform greyscale filter manipulation
            return image.colourspace(VIPS_INTERPRETATION_B_W);
        case FilterType::Sepia: {
            // Perform sepia filter manipulation
            // clang-format off
            std::array<double, 9> sepia = {
                0.3588, 0.7044, 0.1368,
                0.2990, 0.5870, 0.1140,
                0.2392, 0.4696, 0.0912
            };

            auto matrix =
                image.bands() == 3
                    ? VImage::new_from_memory(sepia.begin(), 9 * sizeof(double),
                                              3, 3, 1, VIPS_FORMAT_DOUBLE)
                    : VImage::new_matrixv(4, 4,
                                          sepia[0], sepia[1], sepia[2], 0.0,
                                          sepia[3], sepia[4], sepia[5], 0.0,
                                          sepia[6], sepia[7], sepia[8], 0.0,
                                          0.0, 0.0, 0.0, 1.0);

            return image
                .colourspace(VIPS_INTERPRETATION_sRGB)
                .recomb(matrix);
            // clang-format on
        }
        case FilterType::Duotone: {
            // #C83658 by default
            std::vector<double> start =
                query_->get<Color>("start", Color(255, 200, 54, 88)).to_lab();

            // #D8E74F by default
            std::vector<double> stop =
                query_->get<Color>("stop", Color(255, 216, 231, 79)).to_lab();

            // Perform duotone filter manipulation
            auto lut = VImage::identity() / 255;

            // Makes a lut which is a smooth gradient from start colour to stop
            // colour, with start and stop in CIELAB
            lut = lut * stop + (1 - lut) * start;
            lut = lut.colourspace(
                VIPS_INTERPRETATION_sRGB,
                VImage::option()->set("source_space", VIPS_INTERPRETATION_LAB));

            // The first step to implement a duotone filter is to convert the
            // image to greyscale. The image is then mapped through the lut.
            // Mapping is done by looping over the image and looking up each
            // pixel value in the lut and replacing it with the pre-calculated
            // result.
            if (image.has_alpha()) {
                // Separate alpha channel
                auto image_without_alpha = image.extract_band(
                    0, VImage::option()->set("n", image.bands() - 1));
                auto alpha = image[image.bands() - 1];
                return image_without_alpha.colourspace(VIPS_INTERPRETATION_B_W)
                    .maplut(lut)
                    .bandjoin(alpha);
            }

            return image.colourspace(VIPS_INTERPRETATION_B_W).maplut(lut);
        }
        case FilterType::Negate:
        default:
            // Perform negate filter manipulation
            if (image.has_alpha()) {
                // Separate alpha channel
                auto image_without_alpha = image.extract_band(
                    0, VImage::option()->set("n", image.bands() - 1));
                auto alpha = image[image.bands() - 1];
                return image_without_alpha.invert().bandjoin(alpha);
            }

            return image.invert();
    }
}

}  // namespace weserv::api::processors
