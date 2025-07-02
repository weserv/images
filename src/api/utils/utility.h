#pragma once

#include "../enums.h"

#include <algorithm>
#include <cmath>
#include <ctime>
#include <sstream>
#include <string>
#include <vector>

#include <vips/vips8>
#include <weserv/enums.h>

namespace weserv::api::utils {

using enums::ImageType;
using enums::Output;
using enums::Position;
using vips::VImage;

/**
 * Performs a compile-time version check for libvips.
 */
#define VIPS_VERSION_AT_LEAST(major, minor, patch)                             \
    (((major) < VIPS_MAJOR_VERSION) ||                                         \
     ((major) == VIPS_MAJOR_VERSION && (minor) < VIPS_MINOR_VERSION) ||        \
     ((major) == VIPS_MAJOR_VERSION && (minor) == VIPS_MINOR_VERSION &&        \
      (patch) <= VIPS_MICRO_VERSION))

/**
 * Are pixel values in this image 16-bit integer?
 * @param interpretation The VipsInterpretation.
 * @return A bool indicating if the pixel values in this image are 16-bit.
 */
inline bool is_16_bit(const VipsInterpretation interpretation) {
    return interpretation == VIPS_INTERPRETATION_RGB16 ||
           interpretation == VIPS_INTERPRETATION_GREY16;
}

#if VIPS_VERSION_AT_LEAST(8, 16, 0)
/**
 * Is this image palette-based?
 * @param image The source image.
 * @return A bool indicating whether the image is palette-based.
 */
inline bool is_palette(const VImage &image) {
    return image.get_typeof(VIPS_META_PALETTE) != 0
               ? static_cast<bool>(image.get_int(VIPS_META_PALETTE))
               : false;
}
#endif

/**
 * Does this image have an embedded profile?
 * @param image The source image.
 * @return A bool indicating if this image have an embedded profile.
 */
inline bool has_profile(const VImage &image) {
    return image.get_typeof(VIPS_META_ICC_NAME) != 0;
}

/**
 * Does this image have a non-default density?
 * @param image The source image.
 * @return A bool indicating if this image have a non-default density.
 */
inline bool has_density(const VImage &image) {
    return image.xres() > 1.0;
}

/**
 * Get pixels/mm resolution as pixels/inch density.
 * @param image The source image.
 * @return pixels/inch density.
 */
inline int get_density(const VImage &image) {
    return static_cast<int>(std::round(image.xres() * 25.4));
}

/**
 * Multi-page images can have a page height. Fetch it, and sanity check it.
 * If page-height is not set, it defaults to the image height.
 * @param image The source image.
 * @return The image page height.
 */
inline int get_page_height(const VImage &image) {
    return vips_image_get_page_height(image.get_image());
}

/**
 * Get EXIF Orientation of image, if any.
 * @param image The source image.
 * @return EXIF Orientation.
 */
inline int exif_orientation(const VImage &image) {
    return image.get_typeof(VIPS_META_ORIENTATION) != 0
               ? image.get_int(VIPS_META_ORIENTATION)
               : 0;
}

/**
 * Calculate the rotation for the given angle.
 * @note Assumes that a positive angle is given which is a multiple of 90.
 * @return Rotation as VipsAngle.
 */
inline VipsAngle resolve_angle_rotation(const int angle) {
    switch (angle) {
        case 90:
            return VIPS_ANGLE_D90;
        case 180:
            return VIPS_ANGLE_D180;
        case 270:
            return VIPS_ANGLE_D270;
        default:  // LCOV_EXCL_START
            return VIPS_ANGLE_D0;
    }
    // LCOV_EXCL_STOP
}

/**
 * Determine image extension from the output enum.
 * @note The return value also defines which extension is allowed to
 *       pass on to the selected save operation.
 * @param output The output enum.
 * @return The image extension.
 */
inline std::string determine_image_extension(const Output &output) {
    switch (output) {
        case Output::Jpeg:
            return ".jpg";
        case Output::Webp:
            return ".webp";
        case Output::Avif:
            return ".avif";
        case Output::Tiff:
            return ".tiff";
        case Output::Gif:
            return ".gif";
        case Output::Json:
            return ".json";
        case Output::Png:
        default:
            return ".png";
    }
}

/**
 * Get the supported savers as a comma-separated string.
 * @param msk The savers mask.
 * @return The supported savers as a comma-separated string.
 */
inline std::string supported_savers_string(const uintptr_t msk) {
    std::string result;

    for (int i = 1; i <= 7; ++i) {
        uintptr_t output = 1U << i;
        if ((msk & output) != 0) {
            std::string saver =
                determine_image_extension(static_cast<Output>(output))
                    .substr(1);

            if (result.empty()) {
                result = saver;
            } else {
                result += ", " + saver;
            }
        }
    }

    return result;
}

/**
 * Our ::eval signal callback in case we need to setup progress feedback to
 * abort image computation after a specified time.
 * @param image The image being calculated.
 * @param progress The progress for this image.
 * @param timeout The specified timeout.
 */
static void image_eval_cb(VipsImage *image, VipsProgress *progress,
                          time_t *timeout) {
    if (*timeout > 0 && progress->run >= *timeout) {  // LCOV_EXCL_START
        vips_image_set_kill(image, 1);
        vips_error(
            "weserv",
            "Maximum image processing time of %ld second%s exceeded "
            "with %d second%s. Operation was canceled after %d%% completion",
            *timeout, *timeout > 1 ? "s" : "", progress->run,
            progress->run > 1 ? "s" : "", progress->percent);

        // We've killed the image and issued an error, it's now our caller's
        // responsibility to pass the message up the chain.
        *timeout = 0;
        // LCOV_EXCL_STOP
    }
}

/**
 * Setup progress feedback to abort image evaluation after a specified
 * time, if required.
 * @param image The source image.
 * @param process_timeout The specified process timeout.
 */
inline void setup_timeout_handler(const VImage &image,
                                  const time_t process_timeout) {
    if (process_timeout > 0) {
        VipsImage *vips_image = image.get_image();

        // Keep a private copy of the process timeout here, it will be
        // automatically freed when the image is closed.
        time_t *timeout = VIPS_NEW(vips_image, time_t);
        *timeout = process_timeout;

        g_signal_connect(vips_image, "eval", G_CALLBACK(image_eval_cb),
                         timeout);

        vips_image_set_progress(vips_image, 1);
    }
}

/**
 * Ensure decoding remains sequential.
 * @param image The source image.
 * @param process_timeout The specified process timeout.
 * @return A new image.
 */
inline VImage stay_sequential(const VImage &image,
                              const time_t process_timeout) {
    if (!vips_image_is_sequential(image.get_image())) {
        return image;
    }

    // Copy to memory evaluates the image, so set up the timeout handler,
    // if necessary.
    setup_timeout_handler(image, process_timeout);

    auto copy = image.copy_memory().copy();
    copy.remove(VIPS_META_SEQUENTIAL);
    return copy;
}

/**
 * Determine the output from the image type enum.
 * @param image_type The image type enum.
 * @return The image output.
 */
inline Output to_output(const ImageType &image_type) {
    switch (image_type) {
        case ImageType::Jpeg:
            return Output::Jpeg;
        case ImageType::Webp:
            return Output::Webp;
        case ImageType::Heif:
            return Output::Avif;
        case ImageType::Tiff:
            return Output::Tiff;
        case ImageType::Gif:
            return Output::Gif;
        case ImageType::Png:
        default:
            return Output::Png;
    }
}

/**
 * Determine image type from the name of the load operation.
 * @param loader The name of the load operation.
 * @return The image type.
 */
inline ImageType determine_image_type(const std::string &loader) {
    if (loader.rfind("VipsForeignLoadJpeg", 0) == 0) {
        return ImageType::Jpeg;
    }
    if (loader.rfind("VipsForeignLoadPng", 0) == 0) {
        return ImageType::Png;
    }
    if (loader.rfind("VipsForeignLoadWebp", 0) == 0) {
        return ImageType::Webp;
    }
    if (loader.rfind("VipsForeignLoadTiff", 0) == 0) {
        return ImageType::Tiff;
    }
    if (loader.rfind("VipsForeignLoadNsgif", 0) == 0) {
        return ImageType::Gif;
    }
    if (loader.rfind("VipsForeignLoadSvg", 0) == 0) {
        return ImageType::Svg;
    }
    if (loader.rfind("VipsForeignLoadPdf", 0) == 0) {
        return ImageType::Pdf;
    }
    if (loader.rfind("VipsForeignLoadHeif", 0) == 0) {
        return ImageType::Heif;
    }
    if (loader.rfind("VipsForeignLoadMagick", 0) == 0) {
        return ImageType::Magick;
    }

    return ImageType::Unknown;  // LCOV_EXCL_LINE
}

/**
 * Provide a string identifier for the given image type.
 * @param image_type The image type enum.
 * @return A string identifier for the given image type.
 */
inline std::string image_type_id(const ImageType &image_type) {
    switch (image_type) {
        case ImageType::Jpeg:
            return "jpeg";
        case ImageType::Png:
            return "png";
        case ImageType::Webp:
            return "webp";
        case ImageType::Tiff:
            return "tiff";
        case ImageType::Gif:
            return "gif";
        case ImageType::Svg:
            return "svg";
        case ImageType::Pdf:
            return "pdf";
        case ImageType::Heif:
            return "heif";
        case ImageType::Magick:
            return "magick";
        case ImageType::Unknown:  // LCOV_EXCL_START
        default:
            return "unknown";
        // LCOV_EXCL_STOP
    }
}

/**
 * Does this image type support alpha channel?
 * @param image_type Image type to check.
 * @return A bool indicating if the alpha channel is supported.
 */
inline bool support_alpha_channel(const ImageType &image_type) {
    return image_type == ImageType::Png || image_type == ImageType::Webp ||
           image_type == ImageType::Heif || image_type == ImageType::Tiff ||
           image_type == ImageType::Gif;
}

/**
 * Calculate the (left, top) coordinates of the output image
 * within the input image, applying the given Position.
 * @param in_width In width.
 * @param in_height In height.
 * @param out_width Out width.
 * @param out_height Out height.
 * @param pos The given Position.
 * @return The (left, top) position as pair.
 */
inline std::pair<int, int>
calculate_position(const int in_width, const int in_height, const int out_width,
                   const int out_height, const Position pos) {
    int left = 0;
    int top = 0;
    switch (pos) {
        case Position::Top:
            left = (out_width - in_width) / 2;
            break;
        case Position::Right:
            left = out_width - in_width;
            top = (out_height - in_height) / 2;
            break;
        case Position::Bottom:
            left = (out_width - in_width) / 2;
            top = out_height - in_height;
            break;
        case Position::Left:
            top = (out_height - in_height) / 2;
            break;
        case Position::TopRight:
            left = out_width - in_width;
            break;
        case Position::BottomRight:
            left = out_width - in_width;
            top = out_height - in_height;
            break;
        case Position::BottomLeft:
            top = out_height - in_height;
            break;
        case Position::TopLeft:
            // Do not assign anything here; the default is 0,0
            break;
        default:
            // Centre
            left = (out_width - in_width) / 2;
            top = (out_height - in_height) / 2;
    }

    return std::pair{left, top};
}

/**
 * Split/crop each frame and reassemble.
 * @param image The source image.
 * @param process_timeout The specified process timeout.
 * @param left Crop x-position.
 * @param top Crop y-position.
 * @param width Crop width.
 * @param height Crop height.
 * @param n_pages Number of pages.
 * @param page_height Page height.
 * @return A new image.
 */
inline VImage crop_multi_page(const VImage &image, const time_t process_timeout,
                              int left, int top, int width, int height,
                              int n_pages, int page_height) {
    if (top == 0 && height == page_height) {
        // Fast path; no need to adjust the height of the multi-page image
        return image.extract_area(left, 0, width, image.height());
    }

    std::vector<VImage> pages;
    pages.reserve(n_pages);

    auto crop = stay_sequential(image, process_timeout);

    // Split the image into cropped frames
    for (int i = 0; i < n_pages; i++) {
        pages.push_back(
            crop.extract_area(left, page_height * i + top, width, height));
    }

    // Reassemble the frames into a tall, thin image
    return VImage::arrayjoin(pages, VImage::option()->set("across", 1));
}

/**
 * Calculate the (left, top) coordinates with a given focal point.
 * @param fpx Focal point x-position.
 * @param fpy Focal point y-position.
 * @param in_width Original image width.
 * @param in_height Original image height.
 * @param target_width Target image width.
 * @param target_height Target image height.
 * @param image_width Image width.
 * @param image_height Image height.
 * @return The (left, top) position as pair.
 */
inline std::pair<int, int>
calculate_focal_point(const float fpx, const float fpy, const int in_width,
                      const int in_height, const int target_width,
                      const int target_height, const int image_width,
                      const int image_height) {
    auto ratio_x = static_cast<double>(in_width) / target_width;
    auto ratio_y = static_cast<double>(in_height) / target_height;
    auto factor = std::min(ratio_x, ratio_y);

    auto center_x = (fpx * in_width) / factor;
    auto center_y = (fpy * in_height) / factor;

    auto left = static_cast<int>(std::round(center_x - target_width / 2.0));
    left = std::clamp(left, 0, image_width - target_width);

    auto top = static_cast<int>(std::round(center_y - target_height / 2.0));
    top = std::clamp(top, 0, image_height - target_height);

    return std::pair{left, top};
}

/**
 * Convenient function to convert an image to a JSON representation.
 * @param image The source image.
 * @param image_type Image type of the image.
 * @return A JSON representation of the image.
 */
inline std::string image_to_json(const VImage &image,
                                 const ImageType &image_type) {
    std::ostringstream json;
    json << "{"
         << R"("format":")" << image_type_id(image_type) << "\","
         << R"("width":)" << image.width() << ","
         << R"("height":)" << image.height() << ","
         << R"("space":")"
         << vips_enum_nick(VIPS_TYPE_INTERPRETATION, image.interpretation())
         << "\","
         << R"("channels":)" << image.bands() << ","
         << R"("depth":")"
         << vips_enum_nick(VIPS_TYPE_BAND_FORMAT, image.format()) << "\",";
    if (has_density(image)) {
        json << R"("density":)" << get_density(image) << ",";
    }
    if (image.get_typeof("jpeg-chroma-subsample") != 0) {
        json << R"("chromaSubsampling":")"
             << image.get_string("jpeg-chroma-subsample") << "\",";
    }
    json << R"("isProgressive":)"
         << (image.get_typeof("interlaced") != 0 ? "true" : "false") << ",";
#if VIPS_VERSION_AT_LEAST(8, 16, 0)
    json << R"("isPalette":)" << (is_palette(image) ? "true" : "false") << ",";
#endif
#if VIPS_VERSION_AT_LEAST(8, 15, 0)
    if (image.get_typeof(VIPS_META_BITS_PER_SAMPLE) != 0) {
        json << R"("bitsPerSample":)"
             << image.get_int(VIPS_META_BITS_PER_SAMPLE) << ",";
    }
#endif
    // `palette-bit-depth` is deprecated in favor of `bits-per-sample`.
    if (image.get_typeof("palette-bit-depth") != 0) {
        json << R"("paletteBitDepth":)" << image.get_int("palette-bit-depth")
             << ",";
    }
    if (image.get_typeof(VIPS_META_N_PAGES) != 0) {
        json << R"("pages":)" << image.get_int(VIPS_META_N_PAGES) << ",";
    }
    if (image.get_typeof(VIPS_META_PAGE_HEIGHT) != 0) {
        json << R"("pageHeight":)" << image.get_int(VIPS_META_PAGE_HEIGHT)
             << ",";
    }
    if (image.get_typeof("loop") != 0) {
        json << R"("loop":)" << image.get_int("loop") << ",";
    }
    if (image.get_typeof("delay") != 0) {
        std::vector<int> delays = image.get_array_int("delay");

        json << R"("delay":[)";
        for (auto &delay : delays) {
            json << delay;
            if (&delay != &delays.back()) {
                json << ",";
            }
        }
        json << "],";
    }
    if (image.get_typeof("heif-primary") != 0) {
        json << R"("pagePrimary":)" << image.get_int("heif-primary") << ",";
    }

    json << R"("hasProfile":)" << (has_profile(image) ? "true" : "false") << ","
         << R"("hasAlpha":)" << (image.has_alpha() ? "true" : "false") << ","
         << R"("orientation":)" << exif_orientation(image) << "}";

    return json.str();
}

/**
 * Escape a string by replacing certain special characters.
 * @param s The string to escape.
 * @return The escaped string.
 */
inline std::string escape_string(const std::string &s) {
    std::ostringstream o;
    // LCOV_EXCL_START
    for (char c : s) {
        switch (c) {
            case '\x00':
                o << "\\u0000";
                break;
            case '\x01':
                o << "\\u0001";
                break;
            case '\x0a':
                o << "\\n";
                break;
            case '\x1f':
                o << "\\u001f";
                break;
            case '\x22':
                o << "\\\"";
                break;
            case '\x5c':
                o << "\\\\";
                break;
            default:
                o << c;
        }
    }
    // LCOV_EXCL_STOP

    return o.str();
}

}  // namespace weserv::api::utils
