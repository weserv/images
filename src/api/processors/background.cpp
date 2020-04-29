#include "processors/background.h"

namespace weserv {
namespace api {
namespace processors {

using parsers::Color;

VImage Background::process(const VImage &image) const {
    auto bg = query_->get<Color>("bg", Color::DEFAULT);

    // Don't process the image if:
    // - The image doesn't have an alpha channel.
    // - The background is completely transparent.
    if (!image.has_alpha() || bg.is_transparent()) {
        return image;
    }

    std::vector<double> background_rgba = bg.to_rgba();

    if (image.bands() > 2 && bg.has_alpha_channel()) {
        // If the image has more than two bands and the requested background
        // color has an alpha channel; alpha compositing.

        // Create a new image from a constant that matches the origin image
        // dimensions.
        auto background_image = image.new_from_image(background_rgba);

        // Alpha composite src over dst.
        // Assumes alpha channels are already premultiplied and will be
        // unpremultiplied after.
        return background_image.composite2(
            image, VIPS_BLEND_MODE_OVER,
            VImage::option()->set("premultiplied", true));
    } else {
        // If it's a 8bit-alpha channel image or the requested background color
        // hasn't an alpha channel; then flatten the alpha out of an image,
        // replacing it with a constant background color.
        background_rgba.pop_back();  // Drop the alpha channel

        if (image.bands() <= 2) {
            // Convert sRGB to greyscale
            background_rgba = {0.2126 * background_rgba[0] +
                               0.7152 * background_rgba[1] +
                               0.0722 * background_rgba[2]};
        }

        // The image no longer has an alpha channel
        query_->update("has_alpha", false);

        return image.flatten(
            VImage::option()->set("background", background_rgba));
    }
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
