#include "processors/thumbnail.h"

namespace weserv {
namespace api {
namespace processors {

using namespace enums;

// See `buffer.cpp`
const int MAX_PAGES = 256;

// See `buffer.cpp`
const int MAX_TARGET_SIZE = 71000000;

// See `buffer.cpp`
const bool FAIL_ON_ERROR = false;

// Set to true in order to have a greater advantage of the JPEG
// shrink-on-load feature. You can set this to false for more
// consistent results and to avoid occasional small image shifting.
// NOTE: Can be overridden with `&fsol=0`.
const bool FAST_SHRINK_ON_LOAD = true;

std::pair<double, double> Thumbnail::resolve_shrink(int width,
                                                    int height) const {
    auto rotation = query_->get<int>("angle", 0);
    auto precrop = query_->get<bool>("precrop", false);

    if (!precrop && (rotation == 90 || rotation == 270)) {
        // Swap input width and height when rotating by 90 or 270 degrees
        std::swap(width, height);
    }

    double hshrink = 1.0;
    double vshrink = 1.0;

    auto target_resize_width = query_->get<int>("w");
    auto target_resize_height = query_->get<int>("h");

    auto canvas = query_->get<Canvas>("fit", Canvas::Max);

    if (target_resize_width > 0 && target_resize_height > 0) {
        // Fixed width and height
        hshrink = static_cast<double>(width) /
                  static_cast<double>(target_resize_width);
        vshrink = static_cast<double>(height) /
                  static_cast<double>(target_resize_height);

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
                if (!precrop && (rotation == 90 || rotation == 270)) {
                    std::swap(hshrink, vshrink);
                }
                break;
        }
    } else if (target_resize_width > 0) {
        // Fixed width
        hshrink = static_cast<double>(width) /
                  static_cast<double>(target_resize_width);

        if (canvas != Canvas::IgnoreAspect) {
            // Auto height
            vshrink = hshrink;
        }
    } else if (target_resize_height > 0) {
        // Fixed height
        vshrink = static_cast<double>(height) /
                  static_cast<double>(target_resize_height);

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

    return std::make_pair(hshrink, vshrink);
}

double Thumbnail::resolve_common_shrink(int width, int height) const {
    double hshrink;
    double vshrink;

    std::tie(hshrink, vshrink) = resolve_shrink(width, height);

    double shrink = std::min(hshrink, vshrink);

    // We don't want to pre-shrink so much that we send an axis to 0.
    if (shrink > width || shrink > height) {
        shrink = 1.0;
    }

    return shrink;
}

int Thumbnail::resolve_jpeg_shrink(int width, int height) const {
    double shrink = resolve_common_shrink(width, height);

    int shrink_on_load_factor =
        query_->get<bool>("fsol", FAST_SHRINK_ON_LOAD) ? 1 : 2;

    // Shrink-on-load is a simple block shrink and will
    // add quite a bit of extra sharpness to the image.
    if (shrink >= 8 * shrink_on_load_factor) {
        return 8;
    } else if (shrink >= 4 * shrink_on_load_factor) {
        return 4;
    } else if (shrink >= 2 * shrink_on_load_factor) {
        return 2;
    } else {
        return 1;
    }
}

int Thumbnail::resolve_tiff_pyramid(const VImage &image,
                                    const std::string &buffer, int width,
                                    int height) const {
    int n_pages = 1;
    if (image.get_typeof(VIPS_META_N_PAGES) != 0) {
        n_pages =
            std::max(1, std::min(image.get_int(VIPS_META_N_PAGES), MAX_PAGES));
    }

    // Only one page? Can't be
    if (n_pages < 2) {
        return -1;
    }

    int target_page = -1;

    for (int i = n_pages - 1; i >= 0; i--) {
        auto page =
            VImage::new_from_buffer(buffer, "",
                                    VImage::option()
                                        ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                        ->set("fail", FAIL_ON_ERROR)
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
            // have to finish the loop to be sure.
        }
    }

    return target_page;
}

// TODO(kleisauke): No openslideload_buffer (?)
/*int Thumbnail::resolve_open_slide_level(const VImage &image) const {
    int level_count = 1;
    if (image.get_typeof("openslide.level-count") != 0) {
        level_count = std::max(
            1, std::min(image.get_int("openslide.level-count"), MAX_PAGES));
    }

    for (int level = level_count - 1; level >= 0; level--) {
        auto level_str = "openslide.level[" + std::to_string(level) + "]";
        auto level_width_field = (level_str + ".width").c_str();
        auto level_height_field = (level_str + ".height").c_str();

        if (image.get_typeof(level_width_field) == 0 ||
            image.get_typeof(level_height_field) == 0) {
            continue;
        }

        auto level_width = std::stoi(image.get_string(level_width_field));
        auto level_height = std::stoi(image.get_string(level_height_field));

        if (resolve_common_shrink(level_width, level_height) >= 1.0) {
            return level;
        }
    }

    return 0;
}*/

void Thumbnail::append_page_options(vips::VOption *options) const {
    auto n = query_->get<int>("n");
    auto page = query_->get_if<int>("page",
                                    [](int p) {
                                        // Page needs to be in the range of
                                        // 0 (numbered from zero) - 100000
                                        return p >= 0 && p <= 100000;
                                    },
                                    0);

    options->set("n", n);
    options->set("page", page);
}

VImage Thumbnail::shrink_on_load(const VImage &image,
                                 const std::string &buffer) const {
    // Try to reload input using shrink-on-load, when:
    //  - the width or height parameters are specified.
    //  - gamma correction doesn't need to be applied.
    //  - trimming isn't required.
    if (query_->get<bool>("trim", false) ||
        query_->get<float>("gam", 0.0F) != 0.0F ||
        (query_->get<int>("w") == 0 && query_->get<int>("h") == 0)) {
        return image;
    }

    int width = image.width();
    int height = image.height();

    vips::VOption *load_options = VImage::option()
                                      ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                      ->set("fail", FAIL_ON_ERROR);

    auto image_type = query_->get<ImageType>("type", ImageType::Unknown);

    if (image_type == ImageType::Jpeg) {
        auto shrink = resolve_jpeg_shrink(width, height);

        return VImage::new_from_buffer(buffer, "",
                                       load_options->set("shrink", shrink));
    } else if (image_type == ImageType::Pdf || image_type == ImageType::Webp) {
        append_page_options(load_options);

        auto scale =
            1.0 / resolve_common_shrink(width, utils::get_page_height(image));

        return VImage::new_from_buffer(buffer, "",
                                       load_options->set("scale", scale));
    } else if (image_type == ImageType::Tiff) {
        auto page = resolve_tiff_pyramid(image, buffer, width, height);

        // We've found a pyramid
        if (page != -1) {
            return VImage::new_from_buffer(buffer, "",
                                           load_options->set("page", page));
        }
    /*} else if (image_type == ImageType::OpenSlide) {
        auto level = resolve_open_slide_level(image);

        return VImage::new_from_buffer(buffer, "",
                                       load_options->set("level", level));*/
    } else if (image_type == ImageType::Svg) {
        auto scale = 1.0 / resolve_common_shrink(width, height);

        return VImage::new_from_buffer(buffer, "",
                                       load_options->set("scale", scale));
#if VIPS_VERSION_AT_LEAST(8, 9, 0)
    // Retrieving a non-existent thumbnail from a HEIF image was terribly slow
    // before libvips 8.9.0. See:
    // https://github.com/libvips/libvips/commit/1ef1b2d9870d8be9c1a063a47f0c745c04a127d3
    } else if (image_type == ImageType::Heif) {
        append_page_options(load_options);

        // Fetch the size of the stored thumbnail
        auto thumb = VImage::new_from_buffer(
            buffer, "", load_options->set("thumbnail", true));

        // Use the thumbnail if, by using it, we could get a factor >= * 1.0,
        // ie. we would not need to expand the thumbnail.
        if (resolve_common_shrink(thumb.width(), thumb.height()) >= 1.0) {
            return thumb;
        }
#endif
    }

    // Still here? Just return the original image
    return image;
}

VImage Thumbnail::process(const VImage &image) const {
    // Any pre-shrinking may already have been done
    auto thumb = image;

    // So page_height is after pre-shrink, but before the main shrink stage
    int page_height = utils::get_page_height(thumb);
    query_->update("page_height", page_height);

    // analyzeload_buffer isn't available, so we can safely assume that
    // that we don't have to unpack any radiance images.
    /*if (thumb.coding() == VIPS_CODING_RAD) {
        // rad is scrgb
        thumb = thumb.rad2float();
    }*/

    // If this is a CMYK image, we only want to export at the end
    bool is_cmyk = thumb.interpretation() == VIPS_INTERPRETATION_CMYK;

    // To the processing colourspace. This will unpack LABQ, import CMYK
    // etc.
    thumb = thumb.colourspace(VIPS_INTERPRETATION_sRGB);

    // If there's an alpha, we have to premultiply before shrinking. See
    // https://github.com/libvips/libvips/issues/291
    if (thumb.has_alpha()) {
        thumb = thumb.premultiply();

        query_->update("premultiplied", true);
    }

    int thumb_width = thumb.width();
    int thumb_height = thumb.height();

    double hshrink;
    double vshrink;

    // Shrink to page_height, so we work for multi-page images
    std::tie(hshrink, vshrink) = resolve_shrink(thumb_width, page_height);

    int target_width =
        static_cast<int>(std::rint(static_cast<double>(thumb_width) / hshrink));
    int target_page_height =
        static_cast<int>(std::rint(static_cast<double>(page_height) / vshrink));

    int size;
    if (utils::mul_overflow(target_width, target_page_height, &size) ||
        size > MAX_TARGET_SIZE) {
        throw exceptions::TooLargeImageException(
            "Requested image dimensions are too large. Width x height should "
            "be less than 71 megapixels.");
    }

    // In toilet-roll mode, we must adjust vshrink so that we exactly hit
    // page_height or we'll have pixels straddling pixel boundaries.
    if (thumb_height > page_height) {
        auto n_pages = query_->get<int>("n", 1);
        int target_image_height = target_page_height * n_pages;

        vshrink = static_cast<double>(thumb_height) /
                  static_cast<double>(target_image_height);
    }

    thumb = thumb.resize(1.0 / hshrink,
                         VImage::option()->set("vscale", 1.0 / vshrink));

    query_->update("page_height", target_page_height);

    // Note:
    // Don't unpremultiply the alpha, it's done after all transformations
    // (at the end of the pipeline).

    // Colour management.
    // If this is a CMYK image, just export. Otherwise, we're in
    // device space and we need a combined import/export to transform to
    // the target space.
    if (is_cmyk) {
        thumb = thumb.icc_export(VImage::option()
                                     ->set("output_profile", "srgb")
                                     ->set("intent", VIPS_INTENT_PERCEPTUAL));
    } else if (utils::has_profile(thumb)) {
        thumb = thumb.icc_transform(
            "srgb", VImage::option()
                        // Fallback to srgb
                        ->set("input_profile", "srgb")
                        // Use "perceptual" intent to better match imagemagick
                        ->set("intent", VIPS_INTENT_PERCEPTUAL)
                        ->set("embedded", true));
    }

    return thumb;
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
