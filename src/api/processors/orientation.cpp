#include "orientation.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

VImage Orientation::process(const VImage &image) const {
    auto angle = query_->get<int>("angle", 0);
    auto flip = query_->get<bool>("flip", false);
    auto flop = query_->get<bool>("flop", false);

    // Should we process the image?
    if (angle == 0 && !flip && !flop) {
        return image;
    }

    // Rotate or flip needs random access
    auto output_image =
        angle != 0 || flip
            ? utils::stay_sequential(image, config_.process_timeout)
            : image;

    // Rotation by any multiple of 90 degrees
    if (angle != 0) {
        auto n_pages = query_->get<int>("n");

        // Rearrange the tall image into a vertical grid when rotating a
        // multi-page image with a non-straight angle.
        if (n_pages > 1 && angle != 180) {
            auto page_height = query_->get<int>("page_height");
            auto width = output_image.width();
            output_image = output_image.grid(page_height, n_pages, 1);

            query_->update("page_height", width);
        }

        output_image = output_image.rot(utils::resolve_angle_rotation(angle));
    }

    // Mirror vertically (up-down) about the x-axis
    if (flip) {
        output_image = output_image.flipver();
    }

    // Mirror horizontally (left-right) about the y-axis
    if (flop) {
        output_image = output_image.fliphor();
    }

    // Removing metadata, need to copy the image
    auto copy = output_image.copy();

    // We must remove VIPS_META_ORIENTATION to prevent accidental double
    // rotations
    copy.remove(VIPS_META_ORIENTATION);

    return copy;
}

}  // namespace weserv::api::processors
