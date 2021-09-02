#include "embed.h"

#include "../enums.h"
#include "../utils/utility.h"

#include <tuple>
#include <vector>

namespace weserv {
namespace api {
namespace processors {

using enums::Canvas;
using enums::Position;
using parsers::Color;

VImage Embed::process(const VImage &image) const {
    // Should we process the image?
    if (query_->get<Canvas>("fit", Canvas::Max) != Canvas::Embed) {
        return image;
    }

    int image_width = image.width();
    int image_height = image.height();
    auto width = query_->get_if<int>(
        "w",
        [](int w) {
            // A dimension needs to be higher than 0
            return w > 0;
        },
        image_width);
    auto height = query_->get_if<int>(
        "h",
        [](int h) {
            // A dimension needs to be higher than 0
            return h > 0;
        },
        image_height);

    // Return early when required dimensions are met
    if (image_width == width && image_height == height) {
        return image;
    }

    // A background color can be specified with the cbg parameter
    auto bg = query_->get<Color>("cbg", Color::DEFAULT);

    auto embed_position = query_->get<Position>("a", Position::Center);

    int left;
    int top;
    if (embed_position == Position::Focal) {
        auto fpx = query_->get_if<float>(
            "fpx", [](float x) { return x >= 0.0 && x <= 1.0; }, 0.5F);
        auto fpy = query_->get_if<float>(
            "fpy", [](float y) { return y >= 0.0 && y <= 1.0; }, 0.5F);

        left = static_cast<int>(std::round((width - image_width) * fpx));
        top = static_cast<int>(std::round((height - image_height) * fpy));
    } else {
        std::tie(left, top) = utils::calculate_position(
            image_width, image_height, width, height, embed_position);
    }

    // Leave the height unchanged in toilet-roll mode
    if (query_->get<int>("n", 1) > 1) {
        top = 0;
        height = image_height;
    }

    std::vector<double> background_rgba = bg.to_rgba();
    bool opaque = bg.is_opaque();
    bool has_alpha = image.has_alpha();

    // Drop the alpha channel of the background if it's opaque and the image has
    // no alpha channel
    if (opaque && !has_alpha) {
        background_rgba.pop_back();
    }

    // Internal copy to ensure that the image has an alpha channel, if missing
    auto output_image =
        opaque || has_alpha
            ? image
            : image.bandjoin_const({255});  // Assumes images are always 8-bit

    return output_image.embed(left, top, width, height,
                              VImage::option()
                                  ->set("extend", VIPS_EXTEND_BACKGROUND)
                                  ->set("background", background_rgba));
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
