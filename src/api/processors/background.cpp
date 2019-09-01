#include "processors/background.h"

namespace weserv {
namespace api {
namespace processors {

VImage Background::process(const VImage &image) const {
    auto bg = query_->get<parsers::Color>("bg", parsers::Color::DEFAULT);

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

        // Ensure background is premultiplied sRGB
        if (query_->get<bool>("premultiplied", false)) {
            background_image = background_image.premultiply();
        }

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
        std::vector<double> background_color;
        if (image.bands() > 2) {
            background_color = {background_rgba[0], background_rgba[1],
                                background_rgba[2]};
        } else {
            // Convert sRGB to greyscale
            background_color = {0.2126 * background_rgba[0] +
                                0.7152 * background_rgba[1] +
                                0.0722 * background_rgba[2]};
        }

        auto premultiplied = query_->get<bool>("premultiplied", false);

        // The image no longer has an alpha channel
        query_->update("has_alpha", false);

        // The image isn't premultiplied anymore
        query_->update("premultiplied", false);

        // Flatten on premultiplied images causes weird results,
        // so unpremultiply if we have a premultiplied image.
        return (premultiplied ? image.unpremultiply().cast(VIPS_FORMAT_UCHAR)
                              : image)
            .flatten(VImage::option()->set("background", background_color));
    }
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
