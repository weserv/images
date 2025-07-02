#include "rotation.h"

#include "../utils/utility.h"

#include <vector>

namespace weserv::api::processors {

using parsers::Color;

VImage Rotation::process(const VImage &image) const {
    // Only arbitrary angles are valid
    auto rotation =
        query_->get_if<int>("ro", [](int r) { return r % 90 != 0; }, 0);

    // Should we process the image?
    // Skip for multi-page images
    if (rotation == 0 || query_->get<int>("n") > 1) {
        return image;
    }

    // A background color can be specified with the rbg parameter
    auto bg = query_->get<Color>("rbg", Color::DEFAULT);

    std::vector<double> background = bg.to_rgba();
    bool opaque = bg.is_opaque();
    bool has_alpha = image.has_alpha();

    // Ensure the background has the same number of bands as the image
    if (opaque && !has_alpha) {
        background.pop_back();
    } else if (image.bands() > 4) {
        background.insert(background.end(), image.bands() - 4, background[3]);
    }

    // Internal copy to ensure that the image has an alpha channel, if missing
    auto output_image =
        opaque || has_alpha
            ? image
            : image.bandjoin_const({255});  // Assumes images are always 8-bit

    return utils::stay_sequential(output_image, config_.process_timeout)
        .rotate(rotation, VImage::option()->set("background", background));
}

}  // namespace weserv::api::processors
