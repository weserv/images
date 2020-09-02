#include "processors/alignment.h"

namespace weserv {
namespace api {
namespace processors {

using enums::Canvas;
using enums::Position;

VImage Alignment::process(const VImage &image) const {
    // Should we process the image?
    if (query_->get<Canvas>("fit", Canvas::Max) != Canvas::Crop) {
        return image;
    }

    int image_width = image.width();
    int image_height = image.height();
    auto width = query_->get_if<int>(
        "w",
        [&image_width](int w) {
            // Limit width to image boundary
            return w > 0 && w < image_width;
        },
        image_width);
    auto height = query_->get_if<int>(
        "h",
        [&image_height](int h) {
            // Limit height to image boundary
            return h > 0 && h < image_height;
        },
        image_height);

    // Return early when required dimensions are met
    if (image_width == width && image_height == height) {
        return image;
    }

    auto crop_position = query_->get<Position>("a", Position::Center);

    auto min_width = std::min(image_width, width);
    auto min_height = std::min(image_height, height);

    auto n_pages = query_->get<int>("n");

    // Skip smart crop for multi-page images
    if (n_pages == 1 && (crop_position == Position::Entropy ||
                         crop_position == Position::Attention)) {
        // Need to copy to memory, we have to stay seq
        return image.copy_memory().smartcrop(
            min_width, min_height,
            VImage::option()->set("interesting",
                                  utils::underlying_value(crop_position)));
    } else {
        int left;
        int top;
        if (crop_position == Position::Focal) {
            left = static_cast<int>(
                std::round((image_width - width) *
                           (query_->get<int>("focal_x", 50) / 100.0)));
            top = static_cast<int>(
                std::round((image_height - height) *
                           (query_->get<int>("focal_y", 50) / 100.0)));
        } else {
            std::tie(left, top) = utils::calculate_position(
                width, height, image_width, image_height, crop_position);
        }

        // Leave the height unchanged in toilet-roll mode
        if (n_pages > 1) {
            top = 0;
            min_height = image_height;
        }

        return image.extract_area(left, top, min_width, min_height);
    }
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
