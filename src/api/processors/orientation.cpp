#include "orientation.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

VImage Orientation::process(const VImage &image) const {
    auto exif_orientation = utils::exif_orientation(image);
    auto flip = query_->get<bool>("flip", false);
    auto flop = query_->get<bool>("flop", false);

    // Should we process the image?
    if (exif_orientation == 1 && !flip && !flop) {
        return image;
    }

    // Autorotate or flip needs random access
    auto output_image =
        exif_orientation != 1 || flip
            ? utils::stay_sequential(image, config_.process_timeout)
            : image;

    // Autorotate by EXIF tag
    if (exif_orientation != 1) {
        output_image = output_image.autorot();
    }

    // Mirror vertically (up-down) about the x-axis
    if (flip) {
        output_image = output_image.flipver();
    }

    // Mirror horizontally (left-right) about the y-axis
    if (flop) {
        output_image = output_image.fliphor();
    }

    return output_image;
}

}  // namespace weserv::api::processors
