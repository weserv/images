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
#include <vector>

namespace weserv::api::processors {

using enums::ImageType;
using enums::Output;
using parsers::Coordinate;
using vips::VError;

using io::Blob;
using io::Source;
using io::Target;

template <typename Comparator>
int Stream::resolve_page(const VImage &image, int n_pages, const Source &source,
                         const Blob &blob, const std::string &loader,
                         Comparator comp) const {
    uint64_t size = static_cast<uint64_t>(image.height()) * image.width();

    int target_page = 0;

    for (int i = 1; i < n_pages; ++i) {
        auto image_page =
            new_from_source(source, blob, loader,
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

std::pair<int, int> Stream::get_page_load_options(int n_pages) const {
    // Skip for single-page images
    if (n_pages == 1) {
        return std::pair{1, 0};
    }

    auto page = query_->get_if<int>(
        "page",
        [&n_pages](int p) {
            // Limit page to [0, n_pages]
            // Or:
            //  -1 = largest page
            //  -2 = smallest page
            return p == -1 || p == -2 || (p >= 0 && p <= n_pages);
        },
        0);

    // Selecting the largest/smallest page implies n=1
    if (page == -1 || page == -2) {
        return std::pair{1, page};
    }

    auto n = query_->get_if<int>(
        "n",
        [&n_pages](int n) {
            // Limit number of pages to [1, n_pages]
            // or -1 for all pages (animated GIF/WebP)
            // Note: This is checked against config_.max_pages below.
            return n == -1 || (n >= 1 && n <= n_pages);
        },
        1);

    if (n == -1) {
        // Resolve the number of pages if we need to render until
        // the end of the document.
        n = n_pages - page;
    }

    return std::pair{n, page};
}

VImage Stream::new_from_source(const Source &source, const Blob &blob,
                               const std::string &loader,
                               vips::VOption *options) {
    VImage out_image;

    if (blob != nullptr) {
        // We don't take a copy of the data or free it
        options->set("buffer", blob.get());
    } else {
        options->set("source", source);
    }

    options->set("out", &out_image);

    try {
        VImage::call(loader.c_str(), options);
    } catch (const VError &err) {
        throw exceptions::UnreadableImageException(err.what());
    }

    return out_image;
}

void Stream::resolve_query(const VImage &image) const {
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

    auto image_width = image.width();
    auto image_height = image.height();
    auto target_width = query_->get<Coordinate>("w", Coordinate::INVALID)
                            .to_pixels(image_width);
    auto target_height = query_->get<Coordinate>("h", Coordinate::INVALID)
                             .to_pixels(image_height);
    auto pixel_ratio = query_->get<float>("dpr", -1.0F);

    // Pixel ratio and needs to be in the range of 0 - 8
    if (pixel_ratio >= 0 && pixel_ratio <= 8) {
        target_width = static_cast<int>(
            std::round(static_cast<float>(target_width) * pixel_ratio));
        target_height = static_cast<int>(
            std::round(static_cast<float>(target_height) * pixel_ratio));
    }

    if (exif_orientation > 4 && !query_->get<bool>("precrop", false)) {
        // When the EXIF orientation is greater than 4, swap the target width
        // and height to ensure the behavior aligns with how it would have been
        // if the 90/270 degrees orient had taken place *before* resizing.
        std::swap(target_width, target_height);
    }

    // Update the target width and height parameters, a dimension needs to be:
    // d >= 0 && d <= VIPS_MAX_COORD
    query_->update("w", std::clamp(target_width, 0, VIPS_MAX_COORD));
    query_->update("h", std::clamp(target_height, 0, VIPS_MAX_COORD));

    // Store the original image width and height, handy for the focal point
    // calculations.
    query_->update("input_width", image_width);
    query_->update("input_height", image_height);
}

VImage Stream::new_from_source(const Source &source) const {
    Blob blob;

    const char *loader = vips_foreign_find_load_source(source.get_source());
    if (loader == nullptr) {
        // Try with the old buffer-based loaders
        blob = Blob(vips_source_map_blob(source.get_source()));
        if (blob == nullptr) {
            throw exceptions::InvalidImageException(vips_error_buffer());
        }

        size_t len;
        const void *buf = blob.get_data(&len);

        loader = vips_foreign_find_load_buffer(buf, len);
        if (loader == nullptr) {
            throw exceptions::InvalidImageException(vips_error_buffer());
        }
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

    auto image = new_from_source(source, blob, loader,
                                 VImage::option()
                                     ->set("access", access_method)
                                     ->set("fail", config_.fail_on_error == 1));

    auto n_pages = image.get_typeof(VIPS_META_N_PAGES) != 0
                       ? image.get_int(VIPS_META_N_PAGES)
                       : 1;

    auto n = 1;
    auto page = 0;
    std::tie(n, page) = get_page_load_options(n_pages);

    if (n != 1 || page != 0) {
        // Limit the number of pages
        if (config_.max_pages > 0 && n > config_.max_pages) {
            throw exceptions::TooLargeImageException(
                "Input image exceeds the maximum number of pages. "
                "Number of pages should be less than " +
                std::to_string(config_.max_pages));
        }

        if (page == -1) {
            page = resolve_page(image, n_pages, source, blob, loader,
                                std::greater<>());
        } else if (page == -2) {
            page = resolve_page(image, n_pages, source, blob, loader,
                                std::less<>());
        }

        image = new_from_source(source, blob, loader,
                                VImage::option()
                                    ->set("access", access_method)
                                    ->set("fail", config_.fail_on_error == 1)
                                    ->set("n", n)
                                    ->set("page", page));
    }

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

    // Always store the page load options
    query_->update("n", n);
    query_->update("page", page);

    // Resolve query
    resolve_query(image);

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

    // Enable lossless compression, if necessary
    options->set("lossless", query_->get<bool>("ll", false));

    // Set quality (default is 80)
    options->set("Q", quality);

    // Control the CPU effort spent on improving compression (default 4)
    options->set("effort", static_cast<int>(config_.webp_effort));
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

    // Control the CPU effort spent on improving compression (default 4)
    options->set("effort", static_cast<int>(config_.avif_effort));
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

    // Use JPEG or Deflate compression based on whether lossless output is
    // required
    auto compression = query_->get<bool>("ll", false)
                           ? VIPS_FOREIGN_TIFF_COMPRESSION_DEFLATE
                           : VIPS_FOREIGN_TIFF_COMPRESSION_JPEG;

    // Set quality (default is 80)
    options->set("Q", quality);

    // Set tiff compression format
    options->set("compression", compression);
}

template <>
void Stream::append_save_options<Output::Gif>(vips::VOption *options) const {
    // Control the CPU effort spent on improving compression (default 7)
    options->set("effort", static_cast<int>(config_.gif_effort));
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

        // Write the image to the target
        copy.write_to_target(extension.c_str(), target, save_options);
    }
}

}  // namespace weserv::api::processors
