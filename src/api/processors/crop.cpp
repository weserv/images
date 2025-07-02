#include "crop.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

using parsers::Coordinate;

VImage Crop::process(const VImage &image) const {
    // Should we process the image?
    if (!query_->exists("cx") && !query_->exists("cy") &&
        !query_->exists("cw") && !query_->exists("ch")) {
        return image;
    }

    auto n_pages = query_->get<int>("n");

    int image_width = image.width();

    // Pre-resize extract needs to fetch the page height from the image
    int image_height =
        n_pages > 1
            ? query_->get<int>("page_height", utils::get_page_height(image))
            : image.height();

    auto crop_x = query_->get<Coordinate>("cx", Coordinate::INVALID)
                      .to_pixels(image_width);
    auto crop_y = query_->get<Coordinate>("cy", Coordinate::INVALID)
                      .to_pixels(image_height);

    if (crop_x < 0 || crop_x >= image_width) {
        crop_x = 0;
    }
    if (crop_y < 0 || crop_y >= image_height) {
        crop_y = 0;
    }

    auto crop_w = query_->get<Coordinate>("cw", Coordinate::INVALID)
                      .to_pixels(image_width);
    auto crop_h = query_->get<Coordinate>("ch", Coordinate::INVALID)
                      .to_pixels(image_height);

    int boundary_w = image_width - crop_x;
    int boundary_h = image_height - crop_y;
    if (crop_w <= 0 || crop_w > boundary_w) {
        crop_w = boundary_w;
    }
    if (crop_h <= 0 || crop_h > boundary_h) {
        crop_h = boundary_h;
    }

    if (n_pages > 1) {
        // Update the page height
        query_->update("page_height", crop_h);

        return utils::crop_multi_page(image, config_.process_timeout, crop_x,
                                      crop_y, crop_w, crop_h, n_pages,
                                      image_height);
    }

    return image.extract_area(crop_x, crop_y, crop_w, crop_h);
}

}  // namespace weserv::api::processors
