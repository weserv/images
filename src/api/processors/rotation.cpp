#include "rotation.h"

#include "../utils/utility.h"

#include <vector>

namespace weserv::api::processors {

using parsers::Color;

VImage Rotation::process(const VImage &image) const {
    auto rotation = query_->get<int>("ro", 0);

    // Should we process the image?
    if (rotation == 0) {
        return image;
    }

    auto angle = rotation % 360;
    if (angle < 0) {
        angle = 360 + angle;
    }

    if (angle == 0) {
        return image;
    }

    auto n_pages = query_->get<int>("n");

    // Rotation by a multiple of 90 degrees
    if (angle % 90 == 0) {
        auto output_image = image;

        // Rearrange the tall image into a vertical grid when rotating by
        // 90 or 270 degrees.
        if (n_pages > 1 && angle != 180) {
            auto page_height = query_->get<int>("page_height");
            auto width = output_image.width();
            output_image = output_image.grid(page_height, n_pages, 1);

            query_->update("page_height", width);
        }

        return utils::stay_sequential(output_image, config_.process_timeout)
            .rot(utils::resolve_angle_rotation(angle));
    }

    // Rotating by an arbitrary angle is only supported for single-page images
    if (n_pages > 1) {
        return image;
    }

    // A background color can be specified with the rbg parameter
    auto bg = query_->get<Color>("rbg", Color::DEFAULT);

    // Internal copy to ensure that the image has an alpha channel, if missing
    auto output_image =
        bg.is_opaque() || image.has_alpha()
            ? image
            : image.bandjoin_const({255});  // Assumes images are always 8-bit

    std::vector<double> background = bg.to_rgba();

    // Ensure the background has the same number of bands as the image
    background.resize(output_image.bands(), background[3]);

    return utils::stay_sequential(output_image, config_.process_timeout)
        .rotate(angle, VImage::option()->set("background", background));
}

}  // namespace weserv::api::processors
