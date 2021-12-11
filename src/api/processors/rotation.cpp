#include "rotation.h"

#include "../utils/utility.h"

#include <vector>

namespace weserv {
namespace api {
namespace processors {

using parsers::Color;

VImage Rotation::process(const VImage &image) const {
    // Only arbitrary angles are valid
    auto rotation = query_->get_if<int>(
        "ro", [](int r) { return r % 90 != 0; }, 0);

    // Should we process the image?
    // Skip for multi-page images
    if (rotation == 0 || query_->get<int>("n") > 1) {
        return image;
    }

    // A background color can be specified with the rbg parameter
    auto bg = query_->get<Color>("rbg", Color::DEFAULT);

    std::vector<double> background_rgba = bg.to_rgba();
    bool opaque = bg.is_opaque();
    bool has_alpha = image.has_alpha();

    // Drop the alpha channel of the background if it's opaque and the image has
    // no alpha channel
    if (opaque && !has_alpha) {
        background_rgba.pop_back();
    }

    // Internal copy to ensure that the image has an alpha channel, if missing
    auto output_image =
        opaque || has_alpha
            ? image
            : image.bandjoin_const({255});  // Assumes images are always 8-bit

    // Copy to memory evaluates the image, so set up the timeout handler,
    // if necessary.
    utils::setup_timeout_handler(output_image, config_.process_timeout);

    // Need to copy to memory, we have to stay seq
    return output_image.copy_memory().rotate(
        static_cast<double>(rotation),
        VImage::option()->set("background", background_rgba));
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
