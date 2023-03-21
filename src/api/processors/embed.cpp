#include "embed.h"

#include "../enums.h"
#include "../utils/utility.h"

#include <cmath>
#include <tuple>
#include <vector>

namespace weserv::api::processors {

using enums::Canvas;
using enums::Position;
using parsers::Color;

VImage Embed::embed_multi_page(const VImage &image, int left, int top,
                               int width, int height,
                               const std::vector<double> &background,
                               int n_pages, int page_height) const {
    if (top == 0 && height == page_height) {
        // Fast path; no need to adjust the height of the multi-page image
        return image.embed(left, 0, width, image.height(),
                           VImage::option()
                               ->set("extend", VIPS_EXTEND_BACKGROUND)
                               ->set("background", background));
    }
    if (left == 0 && width == image.width()) {
        // Fast path; no need to adjust the width of the multi-page image
        std::vector<VImage> pages;
        pages.reserve(n_pages);

        // Rearrange the tall image into a vertical grid
        VImage wide = image.grid(page_height, n_pages, 1);

        // Do the embed on the wide image
        wide = wide.embed(0, top, wide.width(), height,
                          VImage::option()
                              ->set("extend", VIPS_EXTEND_BACKGROUND)
                              ->set("background", background));

        // Split the wide image into frames
        for (int i = 0; i < n_pages; i++) {
            pages.push_back(wide.extract_area(width * i, 0, width, height));
        }

        // Reassemble the frames into a tall, thin image
        VImage assembled =
            VImage::arrayjoin(pages, VImage::option()->set("across", 1));

        // Update the page height
        query_->update("page_height", height);

        return assembled;
    }
    // Embedding will always hit the above code paths, below is for reference
    // only and excluded for code coverage
    // LCOV_EXCL_START

    std::vector<VImage> pages;
    pages.reserve(n_pages);

    // Split the image into frames
    for (int i = 0; i < n_pages; i++) {
        pages.push_back(
            image.extract_area(0, page_height * i, image.width(), page_height));
    }

    // Embed each frame in the target size
    for (int i = 0; i < n_pages; i++) {
        pages[i] = pages[i].embed(left, top, width, height,
                                  VImage::option()
                                      ->set("extend", VIPS_EXTEND_BACKGROUND)
                                      ->set("background", background));
    }

    // Reassemble the frames into a tall, thin image
    VImage assembled =
        VImage::arrayjoin(pages, VImage::option()->set("across", 1));

    // Update the page height
    query_->update("page_height", height);

    return assembled;
    // LCOV_EXCL_STOP
}

VImage Embed::process(const VImage &image) const {
    // Should we process the image?
    if (query_->get<Canvas>("fit", Canvas::Max) != Canvas::Embed) {
        return image;
    }

    auto n_pages = query_->get<int>("n");

    int image_width = image.width();
    int image_height =
        n_pages > 1 ? query_->get<int>("page_height") : image.height();

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

    return n_pages > 1
               ? embed_multi_page(output_image, left, top, width, height,
                                  background_rgba, n_pages, image_height)
               : output_image.embed(left, top, width, height,
                                    VImage::option()
                                        ->set("extend", VIPS_EXTEND_BACKGROUND)
                                        ->set("background", background_rgba));
}

}  // namespace weserv::api::processors
