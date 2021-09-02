#include "crop.h"

namespace weserv {
namespace api {
namespace processors {

VImage Crop::process(const VImage &image) const {
    // Should we process the image?
    if (!query_->exists("cx") && !query_->exists("cy") &&
        !query_->exists("cw") && !query_->exists("ch")) {
        return image;
    }

    int image_width = image.width();
    int image_height = image.height();

    auto crop_x = query_->get_if<int>(
        "cx",
        [&image_width](int x) {
            // Limit x coordinate to image width
            return x > 0 && x < image_width;
        },
        0);
    auto crop_y = query_->get_if<int>(
        "cy",
        [&image_height](int y) {
            // Limit y coordinate to image height
            return y > 0 && y < image_height;
        },
        0);

    int boundary_w = image_width - crop_x;
    int boundary_h = image_height - crop_y;

    // Limit coordinates to image boundaries
    auto crop_w = query_->get_if<int>(
        "cw",
        [&boundary_w](int w) {
            // Limit width to image boundary
            return w > 0 && w < boundary_w;
        },
        boundary_w);
    auto crop_h = query_->get_if<int>(
        "ch",
        [&boundary_h](int h) {
            // Limit height to image boundary
            return h > 0 && h < boundary_h;
        },
        boundary_h);

    // Leave the height unchanged in toilet-roll mode
    if (query_->get<int>("n", 1) > 1) {
        crop_y = 0;
        crop_h = image_height;
    }

    return image.extract_area(crop_x, crop_y, crop_w, crop_h);
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
