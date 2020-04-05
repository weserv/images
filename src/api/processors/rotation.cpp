#include "processors/rotation.h"

namespace weserv {
namespace api {
namespace processors {

using parsers::Color;

VImage Rotation::process(const VImage &image) const {
    // Only arbitrary angles are valid
    auto rotation = query_->get_if<int>(
        "ro", [](int r) { return r % 90 != 0; }, 0);

    // Should we process the image?
    // Skip for for multi-page images
    if (rotation == 0 || query_->get<int>("n", 1) > 1) {
        return image;
    }

    // A background color can be specified with the rbg parameter
    auto bg = query_->get<Color>("rbg", Color::DEFAULT);

    if (!query_->get<bool>("has_alpha", false)) {
        // The image may now have an alpha channel
        query_->update("has_alpha", bg.has_alpha_channel());
    }

    // Need to copy to memory, we have to stay seq
    return utils::ensure_alpha(image).copy_memory().rotate(
        static_cast<double>(rotation),
        VImage::option()->set("background", bg.to_rgba()));
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
