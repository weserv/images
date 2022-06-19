#pragma once

#include "base.h"

namespace weserv::api::processors {

class Contrast : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;

 private:
    /**
     * magick's sigmoidal non-linearity contrast control equivalent in libvips.
     *
     * This is a standard contrast adjustment technique: grey values are put
     * through an S-shaped curve which boosts the slope in the mid-tones and
     * drops it for white and black.
     *
     * This will apply to RGB. And takes no account of image gamma, and applies
     * the contrast boost to R, G and B bands, thereby also boosting
     * colourfulness.
     * @param image The source image.
     * @param contrast Strength of the contrast (typically 3-20).
     */
    VImage sigmoid(const VImage &image, double contrast) const;
};

}  // namespace weserv::api::processors
