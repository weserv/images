#include "processors/embed.h"

namespace weserv {
namespace api {
namespace processors {

using namespace enums;

VImage Embed::process(const VImage &image) const {
    // Should we process the image?
    if (query_->get<Canvas>("fit", Canvas::Max) != Canvas::Embed) {
        return image;
    }

    int image_width = image.width();
    int image_height = image.height();
    auto width = query_->get_if<int>("w",
                                     [](int w) {
                                         // A dimension needs to be higher than
                                         // 0
                                         return w > 0;
                                     },
                                     image_width);
    auto height = query_->get_if<int>("h",
                                      [](int h) {
                                          // A dimension needs to be higher than
                                          // 0
                                          return h > 0;
                                      },
                                      image_height);

    // Return early when required dimensions are met
    if (image_width == width && image_height == height) {
        return image;
    }

    // A background color can be specified with the cbg parameter
    auto bg = query_->get<parsers::Color>("cbg", parsers::Color::DEFAULT);

    auto embed_position = query_->get<Position>("a", Position::Center);

    int left;
    int top;
    if (embed_position == Position::Focal) {
        left = static_cast<int>(std::round(
            (width - image_width) * (query_->get<int>("focal_x", 50) / 100.0)));
        top = static_cast<int>(
            std::round((height - image_height) *
                       (query_->get<int>("focal_y", 50) / 100.0)));
    } else {
        std::tie(left, top) = utils::calculate_position(
            image_width, image_height, width, height, embed_position);
    }

    if (!query_->get<bool>("has_alpha", false)) {
        // The image may now have an alpha channel
        query_->update("has_alpha", bg.has_alpha_channel());
    }

    // Leave the height unchanged in toilet-roll mode
    if (query_->get<int>("n", 1) > 1) {
        top = 0;
        height = image_height;
    }

    return utils::ensure_alpha(image).embed(
        left, top, width, height,
        VImage::option()
            ->set("extend", VIPS_EXTEND_BACKGROUND)
            ->set("background", bg.to_rgba()));
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
