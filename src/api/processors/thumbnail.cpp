#include "thumbnail.h"

#include "../exceptions/large.h"
#include "../io/blob.h"
#include "../utils/utility.h"

#include <algorithm>
#include <cmath>
#include <string>

namespace weserv::api::processors {

using enums::Canvas;
using enums::ImageType;

// Set to true in order to have a greater advantage of the JPEG
// shrink-on-load feature. You can set this to false for more
// consistent results and to avoid occasional small image shifting.
// NOTE: Can be overridden with `&fsol=0`.
constexpr bool FAST_SHRINK_ON_LOAD = true;

using io::Blob;
using io::Source;

template <>
VImage
Thumbnail::new_from_source<ImageType::Jpeg>(const Source &source,
                                            vips::VOption *options) const {
    return VImage::jpegload_source(source, options);
}

template <>
VImage
Thumbnail::new_from_source<ImageType::Pdf>(const Source &source,
                                           vips::VOption *options) const {
    return VImage::pdfload_source(source, options);
}

template <>
VImage
Thumbnail::new_from_source<ImageType::Webp>(const Source &source,
                                            vips::VOption *options) const {
    return VImage::webpload_source(source, options);
}

template <>
VImage
Thumbnail::new_from_source<ImageType::Tiff>(const Source &source,
                                            vips::VOption *options) const {
    return VImage::tiffload_source(source, options);
}

// TODO(kleisauke): Support whole-slide images(?)
/*template <>
VImage
Thumbnail::new_from_source<ImageType::OpenSlide>(const Source &source,
                                                 vips::VOption *options) const {
    return VImage::openslideload_source(source, options);
}*/

template <>
VImage
Thumbnail::new_from_source<ImageType::Svg>(const Source &source,
                                           vips::VOption *options) const {
    return VImage::svgload_source(source, options);
}

template <>
VImage
Thumbnail::new_from_source<ImageType::Heif>(const Source &source,
                                            vips::VOption *options) const {
    return VImage::heifload_source(source, options);
}

std::pair<double, double> Thumbnail::resolve_shrink(int width,
                                                    int height) const {
    double hshrink = 1.0;
    double vshrink = 1.0;

    auto target_width = query_->get<int>("w");
    auto target_height = query_->get<int>("h");

    auto canvas = query_->get<Canvas>("fit", Canvas::Max);

    if (target_width > 0 && target_height > 0) {
        // Fixed width and height
        hshrink = static_cast<double>(width) / target_width;
        vshrink = static_cast<double>(height) / target_height;

        switch (canvas) {
            case Canvas::Crop:
            case Canvas::Min:
                if (hshrink < vshrink) {
                    vshrink = hshrink;
                } else {
                    hshrink = vshrink;
                }
                break;
            case Canvas::Embed:
            case Canvas::Max:
                if (hshrink > vshrink) {
                    vshrink = hshrink;
                } else {
                    hshrink = vshrink;
                }
                break;
            case Canvas::IgnoreAspect:
                break;
        }
    } else if (target_width > 0) {
        // Fixed width
        hshrink = static_cast<double>(width) / target_width;

        if (canvas != Canvas::IgnoreAspect) {
            // Auto height
            vshrink = hshrink;
        }
    } else if (target_height > 0) {
        // Fixed height
        vshrink = static_cast<double>(height) / target_height;

        if (canvas != Canvas::IgnoreAspect) {
            // Auto width
            hshrink = vshrink;
        }
    }

    // Should we not enlarge (oversample) the output image?
    if (query_->get<bool>("we", false)) {
        hshrink = std::max(1.0, hshrink);
        vshrink = std::max(1.0, vshrink);
    }

    // We don't want to shrink so much that we send an axis to 0
    hshrink = std::min(hshrink, static_cast<double>(width));
    vshrink = std::min(vshrink, static_cast<double>(height));

    return std::pair{hshrink, vshrink};
}

double Thumbnail::resolve_common_shrink(int width, int height) const {
    auto [hshrink, vshrink] = resolve_shrink(width, height);

    return std::min(hshrink, vshrink);
}

int Thumbnail::resolve_jpeg_shrink(int width, int height) const {
    double shrink = resolve_common_shrink(width, height);
    int shrink_on_load_factor =
        query_->get<bool>("fsol", FAST_SHRINK_ON_LOAD) ? 1 : 2;
    int jpeg_shrink_on_load = 1;

    // Shrink-on-load is a simple block shrink and will
    // add quite a bit of extra sharpness to the image
    if (shrink >= 8 * shrink_on_load_factor) {
        jpeg_shrink_on_load = 8;
    } else if (shrink >= 4 * shrink_on_load_factor) {
        jpeg_shrink_on_load = 4;
    } else if (shrink >= 2 * shrink_on_load_factor) {
        jpeg_shrink_on_load = 2;
    }

    // Lower shrink-on-load for known libjpeg rounding errors
    if (jpeg_shrink_on_load > 1 &&
        static_cast<int>(shrink) == jpeg_shrink_on_load) {
        jpeg_shrink_on_load /= 2;
    }

    return jpeg_shrink_on_load;
}

int Thumbnail::resolve_tiff_pyramid(const VImage &image, const Source &source,
                                    int width, int height) const {
    // Note: This is checked against config_.max_pages in stream.cpp
    int n_pages = image.get_typeof(VIPS_META_N_PAGES) != 0
                      ? image.get_int(VIPS_META_N_PAGES)
                      : 1;

    // Only one page? Can't be
    if (n_pages < 2) {
        return -1;
    }

    int target_page = -1;

    for (int i = n_pages - 1; i >= 0; i--) {
        auto page = new_from_source<ImageType::Tiff>(
            source, VImage::option()
                        ->set("access", VIPS_ACCESS_SEQUENTIAL)
                        ->set("fail", config_.fail_on_error == 1)
                        ->set("page", i));

        int level_width = page.width();
        int level_height = page.height();

        // Try to sanity-check the size of the pages. Do they look
        // like a pyramid?
        int expected_level_width = width / (1 << i);
        int expected_level_height = height / (1 << i);

        // Won't be exact due to rounding etc.
        if (std::abs(level_width - expected_level_width) > 5 ||
            std::abs(level_height - expected_level_height) > 5 ||
            level_width < 2 || level_height < 2) {
            return -1;
        }

        if (target_page == -1 &&
            resolve_common_shrink(level_width, level_height) >= 1.0) {
            target_page = i;

            // We may have found a pyramid, but we
            // have to finish the loop to be sure
        }
    }

    return target_page;
}

// TODO(kleisauke): Support whole-slide images(?)
/*int Thumbnail::resolve_open_slide_level(const VImage &image) const {
    int level_count = 1;
    if (image.get_typeof("openslide.level-count") != 0) {
        level_count =
            std::max(1, std::min(image.get_int("openslide.level-count"),
                                 static_cast<int>(config_.max_pages)));
    }

    for (int level = level_count - 1; level >= 0; level--) {
        auto level_str = "openslide.level[" + std::to_string(level) + "]";
        auto level_width_field = level_str + ".width";
        auto level_height_field = level_str + ".height";

        if (image.get_typeof(level_width_field.c_str()) == 0 ||
            image.get_typeof(level_height_field.c_str()) == 0) {
            continue;
        }

        auto level_width =
            std::stoi(image.get_string(level_width_field.c_str()));
        auto level_height =
            std::stoi(image.get_string(level_height_field.c_str()));

        if (resolve_common_shrink(level_width, level_height) >= 1.0) {
            return level;
        }
    }

    return 0;
}*/

void Thumbnail::append_page_options(vips::VOption *options) const {
    auto n = query_->get<int>("n");
    auto page = query_->get<int>("page");

    options->set("n", n);
    options->set("page", page);
}

VImage Thumbnail::shrink_on_load(const VImage &image,
                                 const Source &source) const {
    // Try to reload input using shrink-on-load, when:
    //  - the width or height parameters are specified
    //  - gamma correction doesn't need to be applied
    //  - trimming isn't required
    if (query_->get<bool>("trim", false) ||
        query_->get<float>("gam", 0.0F) != 0.0F ||
        (query_->get<int>("w") == 0 && query_->get<int>("h") == 0)) {
        return image;
    }

    int width = image.width();
    int height = image.height();

    vips::VOption *load_options = VImage::option()
                                      ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                      ->set("fail", config_.fail_on_error == 1);

    auto image_type = query_->get<ImageType>("type", ImageType::Unknown);

    if (image_type == ImageType::Jpeg) {
        auto shrink = resolve_jpeg_shrink(width, height);

        return new_from_source<ImageType::Jpeg>(
            source, load_options->set("shrink", shrink));
    } else if (image_type == ImageType::Pdf) {
        append_page_options(load_options);

        auto scale =
            1.0 / resolve_common_shrink(width, utils::get_page_height(image));

        return new_from_source<ImageType::Pdf>(
            source, load_options->set("scale", scale));
    } else if (image_type == ImageType::Webp) {
        append_page_options(load_options);

        auto scale =
            1.0 / resolve_common_shrink(width, utils::get_page_height(image));

        // Avoid upsizing via libwebp
        if (scale < 1.0) {
            return new_from_source<ImageType::Webp>(
                source, load_options->set("scale", scale));
        }
    } else if (image_type == ImageType::Tiff) {
        auto page = resolve_tiff_pyramid(image, source, width, height);

        // We've found a pyramid
        if (page != -1) {
            return new_from_source<ImageType::Tiff>(
                source, load_options->set("page", page));
        }
    /*} else if (image_type == ImageType::OpenSlide) {
        auto level = resolve_open_slide_level(image);

        return new_from_source<ImageType::OpenSlide>(
            source, load_options->set("level", level));*/
    } else if (image_type == ImageType::Svg) {
        auto scale = 1.0 / resolve_common_shrink(width, height);

        return new_from_source<ImageType::Svg>(
            source, load_options->set("scale", scale));
    } else if (image_type == ImageType::Heif) {
        append_page_options(load_options);

        // Fetch the size of the stored thumbnail
        auto thumb = new_from_source<ImageType::Heif>(
            source, load_options->set("thumbnail", true));

        // Use the thumbnail if, by using it, we could get a factor > 1.0,
        // i.e. we would not need to expand the thumbnail.
        // Don't use >= since factor can be clipped to 1.0 under some
        // resizing modes.
        return resolve_common_shrink(thumb.width(), thumb.height()) > 1.0
                   ? thumb
                   : image;
    }

    // Still here? The loader probably doesn't support shrink-on-load

    // Delete the options we allocated above
    delete load_options;

    // And return the original image
    return image;
}

// Any pre-shrinking may already have been done
VImage Thumbnail::process(const VImage &image) const {
    auto has_icc_profile = utils::has_profile(image);

    // To the processing colourspace. This will unpack LABQ, import CMYK
    // etc.
    auto thumb = has_icc_profile
                     ? image  // Transformed with a pair of ICC profiles below.
                     : image.colourspace(VIPS_INTERPRETATION_sRGB);

    // So page_height is after pre-shrink, but before the main shrink stage
    // Pre-resize extract needs to fetch the page height from the query holder
    auto page_height =
        query_->get<int>("page_height", utils::get_page_height(thumb));

    int thumb_width = thumb.width();
    int thumb_height = thumb.height();

    // Shrink to page_height, so we work for multi-page images
    auto [hshrink, vshrink] = resolve_shrink(thumb_width, page_height);

    auto target_width =
        static_cast<int>(std::rint(static_cast<double>(thumb_width) / hshrink));
    auto target_page_height =
        static_cast<int>(std::rint(static_cast<double>(page_height) / vshrink));
    auto target_image_height = target_page_height;

    // In toilet-roll mode, we must adjust vshrink so that we exactly hit
    // page_height, or we'll have pixels straddling pixel boundaries
    if (thumb_height > page_height) {
        auto n_pages = query_->get<int>("n");
        target_image_height *= n_pages;

        vshrink = static_cast<double>(thumb_height) / target_image_height;
    }

    // Limit output images to a given number of pixels, where
    // pixels = width * height
    if (config_.limit_output_pixels > 0 &&
        static_cast<uint64_t>(target_width) * target_image_height >
            config_.limit_output_pixels) {
        throw exceptions::TooLargeImageException(
            "Output image exceeds pixel limit. "
            "Width x height should be less than " +
            std::to_string(config_.limit_output_pixels));
    }

    // Both .premultiply() and .unpremultiply() produces a float image, so we
    // must cast back to the original format. Use NOTSET to mean no
    // pre/unmultiply.
    VipsBandFormat unpremultiplied_format = VIPS_FORMAT_NOTSET;

    // If there's an alpha, we have to premultiply before shrinking.
    // See: https://github.com/libvips/libvips/issues/291
    if (thumb.has_alpha() && hshrink != 1.0 && vshrink != 1.0) {
        unpremultiplied_format = thumb.format();

        thumb = thumb.premultiply().cast(unpremultiplied_format);
    }

    thumb = thumb.resize(1.0 / hshrink,
                         VImage::option()->set("vscale", 1.0 / vshrink));

    query_->update("page_height", target_page_height);

    if (unpremultiplied_format != VIPS_FORMAT_NOTSET) {
        thumb = thumb.unpremultiply().cast(unpremultiplied_format);
    }

    // Colour management.
    if (has_icc_profile) {
        // Ensure images with P3 profiles retain full gamut.
        const char *processing_profile =
            image.interpretation() == VIPS_INTERPRETATION_RGB16 ? "p3" : "srgb";

        // If there's some kind of import profile, we can transform to the
        // output.
        thumb = thumb.icc_transform(
            processing_profile,
            VImage::option()
                ->set("embedded", true)
                ->set("depth",
                      utils::is_16_bit(image.interpretation()) ? 16 : 8)
                // Use "perceptual" intent to better match *magick
                ->set("intent", VIPS_INTENT_PERCEPTUAL));
    }

    return thumb;
}

}  // namespace weserv::api::processors
