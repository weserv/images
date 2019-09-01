#include "processors/orientation.h"

namespace weserv {
namespace api {
namespace processors {

VImage Orientation::process(const VImage &image) const {
    auto angle = query_->get<int>("angle", 0);
    auto flip = query_->get<bool>("flip", false);
    auto flop = query_->get<bool>("flop", false);

    // Should we process the image?
    if (angle == 0 && !flip && !flop) {
        return image;
    }

    // Need to copy, since we need to remove metadata
    auto output_image = image;

    // Rotation by any multiple of 90 degrees
    // Skip for for multi-page images
    if (angle != 0 && query_->get<int>("n", 1) == 1) {
        // Need to copy to memory, we have to stay seq
        output_image = output_image.copy_memory().rot(
            utils::resolve_angle_rotation(angle));
    }

    // Flip (mirror about Y axis) if required
    if (flip) {
        output_image = output_image.flipver();
    }

    // Flop (mirror about X axis) if required
    if (flop) {
        output_image = output_image.fliphor();
    }

    // Remove EXIF Orientation from image, if any
    if (output_image.get_typeof(VIPS_META_ORIENTATION) != 0) {
        output_image.remove(VIPS_META_ORIENTATION);
    }

    return output_image;
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
