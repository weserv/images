#include "contrast.h"

#include <cmath>

namespace weserv::api::processors {

VImage Contrast::sigmoid(const VImage &image, const double contrast) const {
    // If true increase the contrast, if false decrease the contrast
    bool sharpen = contrast > 0;

    // Midpoint of the contrast (typically 0.5)
    double midpoint = 0.5;
    double contrast_abs = std::abs(contrast);

    bool ushort = image.format() == VIPS_FORMAT_USHORT;

    /**
     * Make a identity LUT, that is, a lut where each pixel has the value of
     * its index ... if you map an image through the identity, you get the
     * same image back again.
     *
     * LUTs in libvips are just images with either the width or height set
     * to 1, and the 'interpretation' tag set to HISTOGRAM.
     *
     * If 'ushort' is TRUE, we make a 16-bit LUT, ie. 0 - 65535 values;
     * otherwise it's 8-bit (0 - 255)
     */
    auto lut = VImage::identity(VImage::option()->set("ushort", ushort));

    // Rescale so each element is in [0, 1]
    double range = lut.max();
    lut = lut / range;

    VImage result;

    /**
     * The sigmoidal equation, see
     * https://www.imagemagick.org/Usage/color_mods/#sigmoidal
     *
     * and
     * http://osdir.com/ml/video.image-magick.devel/2005-04/msg00006.html
     *
     * Though that's missing a term -- it should be
     * (1/(1+exp(β*(α-u))) - 1/(1+exp(β*α))) /
     *   (1/(1+exp(β*(α-1))) - 1/(1+exp(β*α)))
     *
     * ie. there should be an extra α in the second term
     */
    if (sharpen) {
        auto x = 1 / (1 + (contrast_abs * (midpoint - lut)).exp());
        double min = x.min();
        double max = x.max();

        result = (x - min) / (max - min);
    } else {
        double min = 1 / (1 + std::exp(contrast_abs * midpoint));
        double max = 1 / (1 + std::exp(contrast_abs * (midpoint - 1)));
        auto x = lut * (max - min) + min;

        result = midpoint - ((1 - x) / x).log() / contrast_abs;
    }

    // Rescale back to 0 - 255 or 0 - 65535
    result = result * range;

    // And get the format right ... $result will be a float image after all
    // that maths, but we want uchar or ushort.
    result = result.cast(ushort ? VIPS_FORMAT_USHORT : VIPS_FORMAT_UCHAR);

    return image.maplut(result);
}

VImage Contrast::process(const VImage &image) const {
    auto con = query_->get_if<int>(
        "con",
        [](int c) {
            // Contrast needs to be in the range of
            // -100 - 100
            return c >= -100 && c <= 100;
        },
        0);

    // Should we process the image?
    if (con == 0) {
        return image;
    }

    // Remap contrast from -100/100 to -30/30 range
    double contrast = con * 0.3;

    return sigmoid(image, contrast);
}

}  // namespace weserv::api::processors
