#include "alignment.h"

#include "../enums.h"
#include "../utils/utility.h"

#include <algorithm>
#include <tuple>

namespace weserv::api::processors {

using enums::Canvas;
using enums::Position;

VImage Alignment::process(const VImage &image) const {
    // Should we process the image?
    if (query_->get<Canvas>("fit", Canvas::Max) != Canvas::Crop) {
        return image;
    }

    auto n_pages = query_->get<int>("n");

    int image_width = image.width();
    int image_height =
        n_pages > 1 ? query_->get<int>("page_height") : image.height();

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

    auto crop_width = std::min(image_width, width);
    auto crop_height = std::min(image_height, height);

    // Skip smart crop for multi-page images
    if (n_pages == 1 && (crop_position == Position::Entropy ||
                         crop_position == Position::Attention)) {
        return utils::stay_sequential(image, config_.process_timeout)
            .smartcrop(crop_width, crop_height,
                       VImage::option()->set("interesting",
                                             static_cast<int>(crop_position)));
    }

    int left;
    int top;
    if (crop_position == Position::Focal) {
        auto input_width = query_->get<int>("input_width");
        auto input_height = query_->get<int>("input_height");

        auto fpx = query_->get_if<float>(
            "fpx", [](float x) { return x >= 0.0 && x <= 1.0; }, 0.5F);
        auto fpy = query_->get_if<float>(
            "fpy", [](float y) { return y >= 0.0 && y <= 1.0; }, 0.5F);

        std::tie(left, top) = utils::calculate_focal_point(
            fpx, fpy, input_width, input_height, width, height, image_width,
            image_height);
    } else {
        std::tie(left, top) = utils::calculate_position(
            width, height, image_width, image_height, crop_position);
    }

    if (n_pages > 1) {
        // Update the page height
        query_->update("page_height", crop_height);

        return utils::crop_multi_page(image, config_.process_timeout, left, top,
                                      crop_width, crop_height, n_pages,
                                      image_height);
    }

    return image.extract_area(left, top, crop_width, crop_height);
}

}  // namespace weserv::api::processors
