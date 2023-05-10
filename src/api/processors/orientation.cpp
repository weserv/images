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

    // Internal copy, we need to re-assign a few times
    auto output_image = image;

    // Rotation by any multiple of 90 degrees
    if (angle != 0) {
        // Copy to memory evaluates the image, so set up the timeout handler,
        // if necessary.
        utils::setup_timeout_handler(output_image, config_.process_timeout);

        auto n_pages = query_->get<int>("n");

        // Rearrange the tall image into a vertical grid when rotating a
        // multi-page image with a non-straight angle.
        if (n_pages > 1 && angle != 180) {
            auto page_height = query_->get<int>("page_height");
            auto width = output_image.width();
            output_image = output_image.grid(page_height, n_pages, 1);

            query_->update("page_height", width);
        }

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

    // Removing metadata, need to copy the image
    auto copy = output_image.copy();

    // We must remove VIPS_META_ORIENTATION to prevent accidental double
    // rotations
    copy.remove(VIPS_META_ORIENTATION);

    return copy;
}

}  // namespace weserv::api::processors
