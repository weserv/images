#include "trim.h"

#include "../utils/utility.h"

namespace weserv::api::processors {

VImage Trim::process(const VImage &image) const {
    auto threshold = query_->get_if<int>(
        "trim",
        [](int t) {
            // Threshold needs to be in the
            // range of 1 - 254
            return t >= 1 && t <= 254;
        },
        0);

    // Make sure that trimming is required
    if (threshold == 0 || image.width() < 3 || image.height() < 3) {
        // We could use shrink-on-load for the next thumbnail processor
        query_->update("trim", false);

        return image;
    }

    // Find the value of the pixel at (0, 0), `find_trim` search for all pixels
    // significantly different from this
    auto background = image.extract_area(0, 0, 1, 1);

    // Note: If the image has alpha, we'll need to flatten before `getpoint`
    // to get a correct background value
    if (image.has_alpha()) {
        background = background.flatten();
    }

    // Scale up 8-bit values to match 16-bit input image
    if (utils::is_16_bit(image.interpretation())) {
        threshold = threshold * 256;
    }

    int left, top, width, height;
    left = image.find_trim(&top, &width, &height,
                           VImage::option()
                               ->set("threshold", threshold)
                               ->set("background", background(0, 0)));

    // Sanity check, this usually happens when a high tolerance is specified
    if (width == 0 || height == 0) {
        // We could use shrink-on-load for the next thumbnail processor
        query_->update("trim", false);

        // Just return the original image
        return image;
    }

    // Skip shrink-on-load for the next thumbnail processor
    query_->update("trim", true);

    // Don't trim the height in toilet-roll mode
    if (query_->get<int>("n") > 1) {
        top = 0;
        height = image.height();
    }

    // And crop the original image
    return image.extract_area(left, top, width, height);
}

}  // namespace weserv::api::processors
