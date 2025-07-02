#include "background.h"

#include <vector>

namespace weserv::api::processors {

using parsers::Color;

VImage Background::process(const VImage &image) const {
    auto bg = query_->get<Color>("bg", Color::DEFAULT);

    // Don't process the image if:
    // - The background is completely transparent
    // - The image doesn't have an alpha channel
    if (bg.is_transparent() || !image.has_alpha()) {
        return image;
    }

    std::vector<double> background = bg.to_rgba();

    // Ensure the background has the same number of bands as the image
    // Note: the image could be less than 3 bands when &filt=greyscale is
    // specified
    if (image.bands() < 3) {
        // Convert sRGB to greyscale
        background = {0.2126 * background[0] +
                      0.7152 * background[1] +
                      0.0722 * background[2]};
    } else if (image.bands() > 4) {
        background.insert(background.end(), image.bands() - 4, background[3]);
    } else if (bg.is_opaque()) {
        background.pop_back();
    }

    if (background.size() >= 4) {  // Alpha compositing
        // Create a new image from a constant that matches the origin image
        // dimensions
        auto background_image = image.new_from_image(background);

        // Alpha composite src over dst.
        // Assumes alpha channels are already premultiplied and will be
        // unpremultiplied after.
        return background_image.composite2(
            image, VIPS_BLEND_MODE_OVER,
            VImage::option()->set("premultiplied", true));
    }

    // Flatten the alpha from the image by replacing it with a constant
    // background color.
    return image.flatten(VImage::option()->set("background", background));
}

}  // namespace weserv::api::processors
