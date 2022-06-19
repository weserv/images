#pragma once

#include "base.h"

namespace weserv::api::processors {

class Embed : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;

 private:
    /**
     * Split into frames, embed each frame, reassemble, and update page height.
     * @param image The source image.
     * @param left Embed x-position.
     * @param top Embed y-position.
     * @param width Embed width.
     * @param height Embed height.
     * @param background Embed background color.
     * @param n_pages Number of pages.
     * @param page_height Page height.
     * @return A new image.
     */
    VImage embed_multi_page(const VImage &image, int left, int top, int width,
                            int height, const std::vector<double> &background,
                            int n_pages, int page_height) const;
};

}  // namespace weserv::api::processors
