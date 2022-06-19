#include "stream.h"

#include "../exceptions/invalid.h"
#include "../exceptions/large.h"
#include "../exceptions/unreadable.h"
#include "../exceptions/unsupported.h"
#include "../utils/utility.h"

#include <algorithm>
#include <cstddef>
#include <cstdint>
#include <functional>
#include <tuple>

namespace weserv {
namespace api {
namespace processors {

using enums::ImageType;
using enums::Output;
using vips::VError;

using io::Source;
using io::Target;

template <typename Comparator>
int Stream::resolve_page(const Source &source, const std::string &loader,
                         Comparator comp) const {
    auto image = new_from_source(source, loader,
                                 VImage::option()
                                     ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                     ->set("fail", config_.fail_on_error == 1)
                                     ->set("page", 0));

    int n_pages = image.get_typeof(VIPS_META_N_PAGES) != 0
                      ? image.get_int(VIPS_META_N_PAGES)
                      : 1;

    // Limit the number of pages
    if (config_.max_pages > 0 && n_pages > config_.max_pages) {
        throw exceptions::TooLargeImageException(
            "Input image exceeds the maximum number of pages. "
            "Number of pages should be less than " +
            std::to_string(config_.max_pages));
    }

    uint64_t size = static_cast<uint64_t>(image.height()) * image.width();

    int target_page = 0;

    for (int i = 1; i < n_pages; ++i) {
        auto image_page =
            new_from_source(source, loader,
                            VImage::option()
                                ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                ->set("fail", config_.fail_on_error == 1)
                                ->set("page", i));

        uint64_t page_size =
            static_cast<uint64_t>(image_page.height()) * image_page.width();

        if (comp(page_size, size)) {
            target_page = i;
            size = page_size;
        }
    }

    return target_page;
}

std::pair<int, int>
Stream::get_page_load_options(const Source &source,
                              const std::string &loader) const {
    auto n = query_->get_if<int>(
        "n",
        [](int p) {
            // Number of pages needs to be higher than 0
            // or -1 for all pages (animated GIF/WebP)
            // Note: This is checked against config_.max_pages below.
            return p == -1 || p > 0;
        },
        1);

    auto page = query_->get_if<int>(
        "page",
        [](int p) {
            // Page needs to be in the range of
            // 0 (numbered from zero) - 100000
            // Or:
            //  -1 = largest page
            //  -2 = smallest page
            return p == -1 || p == -2 || (p >= 0 && p <= 100000);
        },
        0);

    if (page != -1 && page != -2) {
        return std::make_pair(n, page);
    }

    if (page == -1) {
        page = resolve_page(source, loader, std::greater<uint64_t>());
    } else {  // page == -2
        page = resolve_page(source, loader, std::less<uint64_t>());
    }

    // Update page according to new value
    query_->update("page", page);

    return std::make_pair(n, page);
}

VImage Stream::new_from_source(const Source &source, const std::string &loader,
                               vips::VOption *options) const {
    VImage out_image;

#ifdef WESERV_ENABLE_TRUE_STREAMING
    try {
        VImage::call(loader.c_str(),
                     options->set("source", source)->set("out", &out_image));
#else
    // We don't take a copy of the data or free it
    auto *blob =
        vips_blob_new(nullptr, source.buffer().data(), source.buffer().size());
    options = options->set("buffer", blob)->set("out", &out_image);
    vips_area_unref(reinterpret_cast<VipsArea *>(blob));

    try {
        VImage::call(loader.c_str(), options);
#endif
    } catch (const VError &err) {
        throw exceptions::UnreadableImageException(err.what());
    }

    return out_image;
}

void Stream::resolve_dimensions() const {
    auto width = query_->get<int>("w", 0);
    auto height = query_->get<int>("h", 0);
    auto pixel_ratio = query_->get<float>("dpr", -1.0F);

    // Pixel ratio and needs to be in the range of 0 - 8
    if (pixel_ratio >= 0 && pixel_ratio <= 8) {
        width = static_cast<int>(
            std::round(static_cast<float>(width) * pixel_ratio));
        height = static_cast<int>(
            std::round(static_cast<float>(height) * pixel_ratio));
    }

    // Update the width and height parameters,
    // a dimension needs to be d >= 0 && d <= VIPS_MAX_COORD.
    query_->update("w", utils::clamp(width, 0, VIPS_MAX_COORD));
    query_->update("h", utils::clamp(height, 0, VIPS_MAX_COORD));
}

void Stream::resolve_rotation_and_flip(const VImage &image) const {
    auto rotate = query_->get_if<int>(
        "ro",
        [](int r) {
            // Only positive or negative angles
            // that are a multiple of 90 degrees
            // are valid.
            return r % 90 == 0;
        },
        0);

    auto flip = query_->get<bool>("flip", false);
    auto flop = query_->get<bool>("flop", false);

    auto exif_orientation = utils::exif_orientation(image);
    switch (exif_orientation) {
        case 6:
            rotate = rotate + 90;
            break;
        case 3:
            rotate = rotate + 180;
            break;
        case 8:
            rotate = rotate + 270;
            break;
        case 2:  // flop 1
            flop = true;
            break;
        case 7:  // flip 6
            flip = true;
            rotate = rotate + 90;
            break;
        case 4:  //  flop 3
            flop = true;
            rotate = rotate + 180;
            break;
        case 5:  // flip 8
            flip = true;
            rotate = rotate + 270;
            break;
        default:
            break;
    }

    int angle = rotate % 360;
    if (angle < 0) {
        angle = 360 + angle;
    }

    // Update the angle of rotation and need-to-flip parameters
    query_->update("angle", angle);
    query_->update("flip", flip);
    query_->update("flop", flop);
}

VImage Stream::new_from_source(const Source &source) const {
#ifdef WESERV_ENABLE_TRUE_STREAMING
    const char *loader = vips_foreign_find_load_source(source.get_source());
#else
    const char *loader = vips_foreign_find_load_buffer(source.buffer().data(),
                                                       source.buffer().size());
#endif

    if (loader == nullptr) {
        throw exceptions::InvalidImageException(vips_error_buffer());
    }

    ImageType image_type = utils::determine_image_type(loader);

    // Save the image type so that we can work out
    // what options to pass to write_to_target()
    query_->update("type", static_cast<int>(image_type));

    // Don't use sequential mode read, if we're doing a trim.
    // (it will scan the whole image once to find the crop area)
    auto access_method = query_->get<int>("trim", 0) != 0
                             ? VIPS_ACCESS_RANDOM
                             : VIPS_ACCESS_SEQUENTIAL;

    vips::VOption *options;
    int n = 1;
    int page = 0;
    if (utils::support_multi_pages(image_type)) {
        std::tie(n, page) = get_page_load_options(source, loader);

        options = VImage::option()
                      ->set("access", access_method)
                      ->set("fail", config_.fail_on_error == 1)
                      ->set("n", n)
                      ->set("page", page);
    } else {
        options = VImage::option()
                      ->set("access", access_method)
                      ->set("fail", config_.fail_on_error == 1);
    }

    auto image = new_from_source(source, loader, options);

    // Limit input images to a given number of pixels, where
    // pixels = width * height
    if (config_.limit_input_pixels > 0 &&
        static_cast<uint64_t>(image.width()) * image.height() >
            config_.limit_input_pixels) {
        throw exceptions::TooLargeImageException(
            "Input image exceeds pixel limit. "
            "Width x height should be less than " +
            std::to_string(config_.limit_input_pixels));
    }

    if (n == -1) {
        // Resolve the number of pages if we need to render until
        // the end of the document.
        n = image.get_typeof(VIPS_META_N_PAGES) != 0
                ? image.get_int(VIPS_META_N_PAGES) - page
                : 1;
    }

    // Limit the number of pages
    if (config_.max_pages > 0 && n > config_.max_pages) {
        throw exceptions::TooLargeImageException(
            "Input image exceeds the maximum number of pages. "
            "Number of pages should be less than " +
            std::to_string(config_.max_pages));
    }

    // Always store the number of pages to load
    query_->update("n", n);

    // Resolve target dimensions
    resolve_dimensions();

    // Resolve the angle of rotation and need-to-flip
    // for the given exif orientation and query parameters.
    resolve_rotation_and_flip(image);

    // Store the original image width and height, handy for the focal point
    // calculations.
    query_->update("input_width", image.width());
    query_->update("input_height", image.height());

    return image;
}

template <>
void Stream::append_save_options<Output::Jpeg>(vips::VOption *options) const {
    auto quality = query_->get_if<int>(
        "q",
        [](int q) {
            // Quality needs to be in the range
            // of 1 - 100
            return q >= 1 && q <= 100;
        },
        static_cast<int>(config_.jpeg_quality));

    // Set quality (default is 80)
    options->set("Q", quality);

    // Use progressive (interlace) scan, if necessary
    options->set("interlace", query_->get<bool>("il", false));

    // Enable libjpeg's Huffman table optimiser
    options->set("optimize_coding", true);
}

template <>
void Stream::append_save_options<Output::Png>(vips::VOption *options) const {
    auto level = query_->get_if<int>(
        "l",
        [](int l) {
            // Level needs to be in the range of
            // 0 (no Deflate) - 9 (maximum Deflate)
            return l >= 0 && l <= 9;
        },
        static_cast<int>(config_.zlib_level));

    auto filter = query_->get<bool>("af", false) ? VIPS_FOREIGN_PNG_FILTER_ALL
                                                 : VIPS_FOREIGN_PNG_FILTER_NONE;

    // Use progressive (interlace) scan, if necessary
    options->set("interlace", query_->get<bool>("il", false));

    // Set zlib compression level (default is 6)
    options->set("compression", level);

    // Use adaptive row filtering (default is none)
    options->set("filter", filter);
}

template <>
void Stream::append_save_options<Output::Webp>(vips::VOption *options) const {
    auto quality = query_->get_if<int>(
        "q",
        [](int q) {
            // Quality needs to be in the range
            // of 1 - 100
            return q >= 1 && q <= 100;
        },
        static_cast<int>(config_.webp_quality));

    // Set quality (default is 80)
    options->set("Q", quality);
}

template <>
void Stream::append_save_options<Output::Avif>(vips::VOption *options) const {
    auto quality = query_->get_if<int>(
        "q",
        [](int q) {
            // Quality needs to be in the range
            // of 1 - 100
            return q >= 1 && q <= 100;
        },
        static_cast<int>(config_.avif_quality));

    // Set quality (default is 80)
    options->set("Q", quality);

    // Set compression format to AV1
    options->set("compression", VIPS_FOREIGN_HEIF_COMPRESSION_AV1);

#if VIPS_VERSION_AT_LEAST(8, 10, 2)
    // Control the CPU effort spent on improving compression (default 4)
    options->set("speed", 9 - static_cast<int>(config_.avif_effort));
#endif
}

template <>
void Stream::append_save_options<Output::Tiff>(vips::VOption *options) const {
    auto quality = query_->get_if<int>(
        "q",
        [](int q) {
            // Quality needs to be in the range
            // of 1 - 100
            return q >= 1 && q <= 100;
        },
        static_cast<int>(config_.tiff_quality));

    // Set quality (default is 80)
    options->set("Q", quality);

    // Set the tiff compression to jpeg
    options->set("compression", "jpeg");
}

template <>
void Stream::append_save_options<Output::Gif>(vips::VOption *options) const {
// libvips 8.12 features a gifsave operation that uses cgif and libimagequant
#if VIPS_VERSION_AT_LEAST(8, 12, 0)
    // Control the CPU effort spent on improving compression (default 7)
    options->set("effort", static_cast<int>(config_.gif_effort));
#else  // libvips prior to 8.12 uses *magick for saving to gif
    // Set the format option to hint the file type
    options->set("format", "gif");
#endif
}

void Stream::append_save_options(const Output &output,
                                 vips::VOption *options) const {
    switch (output) {
        case Output::Jpeg:
            append_save_options<Output::Jpeg>(options);
            break;
        case Output::Webp:
            append_save_options<Output::Webp>(options);
            break;
        case Output::Avif:
            append_save_options<Output::Avif>(options);
            break;
        case Output::Tiff:
            append_save_options<Output::Tiff>(options);
            break;
        case Output::Gif:
            append_save_options<Output::Gif>(options);
            break;
        case Output::Png:
        default:
            append_save_options<Output::Png>(options);
            break;
    }
}

void Stream::write_to_target(const VImage &image, const Target &target) const {
    // Attaching metadata, need to copy the image
    auto copy = image.copy();

    // Only update page height if we have more than one page, or this could
    // accidentally turn into an animated image later.
    // See: https://github.com/weserv/images/issues/242
    if (query_->get<int>("n") > 1) {
        copy.set(VIPS_META_PAGE_HEIGHT, query_->get<int>("page_height"));
    }

    // Set the number of loops, libvips uses iterations like this:
    // 0 - set 0 loops (infinite)
    // 1 - loop once
    // 2 - loop twice etc.
    auto loop = query_->get<int>("loop", -1);
    if (loop >= 0) {
        copy.set("loop", loop);
    }

    // Set the frame delay(s)
    auto delays = query_->get_if<std::vector<int>>(
        "delay",
        [](std::vector<int> v) {
            // A single delay must be greater than or equal to zero.
            return std::all_of(v.begin(), v.end(),
                               [](int d) { return d >= 0; });
        },
        {});
    if (!delays.empty()) {
        if (delays.size() == 1) {
            // We have just one delay, repeat that value for all frames
            delays.insert(delays.end(), query_->get<int>("n") - 1, delays[0]);
        }

        copy.set("delay", delays);
    }

    auto output = query_->get<Output>("output", Output::Origin);
    auto image_type = query_->get<ImageType>("type", ImageType::Unknown);

    if (output == Output::Origin) {
        // We force the output to PNG if the image has alpha and doesn't have
        // the right extension to output alpha (useful for masking and
        // embedding).
        output = utils::support_alpha_channel(image_type) || !copy.has_alpha()
                     ? utils::to_output(image_type)
                     : Output::Png;
    }

    std::string extension = utils::determine_image_extension(output);

    if ((config_.savers & static_cast<uintptr_t>(output)) == 0) {
        throw exceptions::UnsupportedSaverException(
            "Saving to " + extension.substr(1) +
            " is disabled. Supported savers: " +
            utils::supported_savers_string(config_.savers));
    }

    if (output == Output::Json) {
        std::string out = utils::image_to_json(copy, image_type);

        target.setup(extension);
        target.write(out.c_str(), out.size());
        target.end();
    } else {
        // Strip all metadata (EXIF, XMP, IPTC).
        // (all savers supports this option)
        vips::VOption *save_options = VImage::option()->set("strip", true);

        append_save_options(output, save_options);

        target.setup(extension);

        // Set up the timeout handler, if necessary
        utils::setup_timeout_handler(copy, config_.process_timeout);

#ifdef WESERV_ENABLE_TRUE_STREAMING
        // Write the image to the target
        copy.write_to_target(extension.c_str(), target, save_options);
#else
        void *buf;
        size_t size;

        // Write the image to a formatted string
        copy.write_to_buffer(extension.c_str(), &buf, &size, save_options);

        target.write(buf, size);
        target.end();

        g_free(buf);
#endif
    }
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
