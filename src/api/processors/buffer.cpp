#include "processors/buffer.h"

namespace weserv {
namespace api {
namespace processors {

using namespace enums;
using vips::VError;

// Should be plenty
const int MAX_PAGES = 256;

// The default quality of 85 usually produces excellent results
const int DEFAULT_QUALITY = 85;

// A default compromise between speed and compression (Z_DEFAULT_COMPRESSION)
const int DEFAULT_LEVEL = 6;

// = 71 megapixels
const int MAX_IMAGE_SIZE = 71000000;

// Do a "best effort" to decode images, even if the data is corrupt or invalid.
// Set this flag to `true` if you would rather to halt processing and raise an
// error when loading invalid images.
// See: CVE-2019-6976
// https://blog.silentsignal.eu/2019/04/18/drop-by-drop-bleeding-through-libvips/
// https://github.com/weserv/images/issues/194
const bool FAIL_ON_ERROR = false;

template <typename Comparator>
int ImageBuffer::resolve_page(const std::string &buf, const std::string &loader,
                              Comparator comp) const {
    auto image = new_from_buffer(buf, loader,
                                 VImage::option()
                                     ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                     ->set("fail", FAIL_ON_ERROR)
                                     ->set("page", 0));

    int n_pages = 1;
    if (image.get_typeof(VIPS_META_N_PAGES) != 0) {
        n_pages =
            std::max(1, std::min(image.get_int(VIPS_META_N_PAGES), MAX_PAGES));
    }

    uint64_t size = static_cast<uint64_t>(image.height()) *
                    static_cast<uint64_t>(image.width());

    int target_page = 0;

    for (int i = 1; i < n_pages; ++i) {
        auto image_page =
            new_from_buffer(buf, loader,
                            VImage::option()
                                ->set("access", VIPS_ACCESS_SEQUENTIAL)
                                ->set("fail", FAIL_ON_ERROR)
                                ->set("page", i));

        uint64_t page_size = static_cast<uint64_t>(image_page.height()) *
                             static_cast<uint64_t>(image_page.width());

        if (comp(page_size, size)) {
            target_page = i;
            size = page_size;
        }
    }

    return target_page;
}

std::pair<int, int>
ImageBuffer::get_page_load_options(const std::string &buf,
                                   const std::string &loader) const {
    auto n =
        query_->get_if<int>("n",
                            [](int p) {
                                // Number of pages needs to be in the range
                                // of 1 - 256
                                // -1 for all pages (animated GIF/WebP)
                                return p == -1 || (p >= 1 && p <= MAX_PAGES);
                            },
                            1);

    auto page = query_->get_if<int>("page",
                                    [](int p) {
                                        // Page needs to be in the range of
                                        // 0 (numbered from zero) - 100000
                                        // Or:
                                        //  -1 = largest page
                                        //  -2 = smallest page
                                        return p == -1 || p == -2 ||
                                               (p >= 0 && p <= 100000);
                                    },
                                    0);

    if (page != -1 && page != -2) {
        return std::make_pair(n, page);
    }

    if (page == -1) {
        page = resolve_page(buf, loader, std::greater<uint64_t>());
    } else {  // page == -2
        page = resolve_page(buf, loader, std::less<uint64_t>());
    }

    // Update page according to new value
    query_->update("page", page);

    return std::make_pair(n, page);
}

VImage ImageBuffer::new_from_buffer(const std::string &buf,
                                    const std::string &loader,
                                    vips::VOption *options) const {
    VImage out_image;

    // We don't take a copy of the data or free it
    auto *blob = vips_blob_new(nullptr, buf.c_str(), buf.size());
    options = options->set("buffer", blob)->set("out", &out_image);
    vips_area_unref(reinterpret_cast<VipsArea *>(blob));

    try {
        VImage::call(loader.c_str(), options);
    } catch (const VError &err) {
        throw exceptions::UnreadableImageException(err.what());
    }

    return out_image;
}

void ImageBuffer::resolve_dimensions() const {
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
    query_->update("w", std::max(0, std::min(width, VIPS_MAX_COORD)));
    query_->update("h", std::max(0, std::min(height, VIPS_MAX_COORD)));
}

void ImageBuffer::resolve_rotation_and_flip(const VImage &image) const {
    auto rotate = query_->get_if<int>("ro",
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

VImage ImageBuffer::from_buffer(const std::string &buf) const {
    const char *loader = vips_foreign_find_load_buffer(buf.c_str(), buf.size());

    if (loader == nullptr) {
        throw exceptions::InvalidImageException(vips_error_buffer());
    }

    // Save the image type so that we can work out
    // what options to pass to write_to_buffer()
    query_->update(
        "type", utils::underlying_value(utils::determine_image_type(loader)));

    // Don't use sequential mode read, if we're doing a trim.
    // (it will scan the whole image once to find the crop area)
    auto access_method = query_->get<int>("trim", 0) != 0
                             ? VIPS_ACCESS_RANDOM
                             : VIPS_ACCESS_SEQUENTIAL;

    vips::VOption *options = VImage::option()
                                 ->set("access", access_method)
                                 ->set("fail", FAIL_ON_ERROR);

    int n = 1;
    if (utils::image_loader_supports_page(loader)) {
        int page = 0;
        std::tie(n, page) = get_page_load_options(buf, loader);

        options->set("n", n);
        options->set("page", page);
    }

    auto image = new_from_buffer(buf, loader, options);

    int size;
    if (utils::mul_overflow(image.width(), image.height(), &size) ||
        size > MAX_IMAGE_SIZE) {
        throw exceptions::TooLargeImageException(
            "Image is too large for processing. Width x height should be less "
            "than 71 megapixels.");
    }

    if (n == -1) {
        // Resolve the number of pages if we need to render until
        // the end of the document.
        n = image.get_typeof(VIPS_META_N_PAGES) != 0
                ? std::max(
                      1, std::min(image.get_int(VIPS_META_N_PAGES), MAX_PAGES))
                : 1;
    }

    // Always store the number of pages to load
    query_->update("n", n);

    // Resolve target dimensions
    resolve_dimensions();

    // Resolve the angle of rotation and need-to-flip
    // for the given exif orientation and query parameters.
    resolve_rotation_and_flip(image);

    // We need to store the image alpha channel predicate in the query map
    // because some libvips operations (for e.g. composite and embed) may change
    // the alpha.
    query_->update("has_alpha", image.has_alpha());

    return image;
}

template <>
void ImageBuffer::append_save_options<Output::Jpeg>(
    vips::VOption *options) const {
    auto quality = query_->get_if<int>("q",
                                       [](int q) {
                                           // Quality needs to be in the range
                                           // of 1 - 100
                                           return q >= 1 && q <= 100;
                                       },
                                       DEFAULT_QUALITY);

    // Set quality (default is 85)
    options->set("Q", quality);

    // Use progressive (interlace) scan, if necessary
    options->set("interlace", query_->get<bool>("il", false));

    // Enable libjpeg's Huffman table optimiser
    options->set("optimize_coding", true);
}

template <>
void ImageBuffer::append_save_options<Output::Png>(
    vips::VOption *options) const {
    auto level = query_->get_if<int>("l",
                                     [](int l) {
                                         // Level needs to be in the range of
                                         // 0 (no Deflate) - 9 (maximum Deflate)
                                         return l >= 0 && l <= 9;
                                     },
                                     DEFAULT_LEVEL);

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
void ImageBuffer::append_save_options<Output::Webp>(
    vips::VOption *options) const {
    auto quality = query_->get_if<int>("q",
                                       [](int q) {
                                           // Quality needs to be in the range
                                           // of 1 - 100
                                           return q >= 1 && q <= 100;
                                       },
                                       DEFAULT_QUALITY);

    // Set quality (default is 85)
    options->set("Q", quality);

    // Set quality of alpha layer to 100
    options->set("alpha_q", 100);
}

template <>
void ImageBuffer::append_save_options<Output::Tiff>(
    vips::VOption *options) const {
    auto quality = query_->get_if<int>("q",
                                       [](int q) {
                                           // Quality needs to be in the range
                                           // of 1 - 100
                                           return q >= 1 && q <= 100;
                                       },
                                       DEFAULT_QUALITY);

    // Set quality (default is 85)
    options->set("Q", quality);

    // Set the tiff compression to jpeg
    options->set("compression", "jpeg");
}

template <>
void ImageBuffer::append_save_options<Output::Gif>(
    vips::VOption *options) const {
    // Set the format option to hint the file type
    options->set("format", "gif");
}

void ImageBuffer::append_save_options(const Output &output,
                                      vips::VOption *options) const {
    switch (output) {
        case Output::Jpeg:
            append_save_options<Output::Jpeg>(options);
            break;
        case Output::Webp:
            append_save_options<Output::Webp>(options);
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

std::pair<std::string, std::string>
ImageBuffer::to_buffer(const VImage &image) const {
    // Need to copy, since we need to attach metadata
    auto output_image = image;

    // All image processors were successful, reverse
    // premultiplication after all transformations.
    if (query_->get<bool>("premultiplied", false)) {
        // Unpremultiply image alpha and cast pixel values to integer
        output_image = output_image.unpremultiply().cast(VIPS_FORMAT_UCHAR);
    }

    // Update page height
    if (output_image.get_typeof(VIPS_META_PAGE_HEIGHT) != 0) {
        output_image.set(VIPS_META_PAGE_HEIGHT,
                         query_->get<int>("page_height"));
    }

    // Set the number of loops, libvips uses iterations like this:
    // 0 - set 0 loops (infinite)
    // 1 - loop once
    // 2 - loop twice etc.
    auto loop = query_->get<int>("loop", -1);
    if (loop != -1) {
        output_image.set("gif-loop", loop);
    }

    // Set the frame delay(s)
    auto delays = query_->get<std::vector<int>>("delay", {});
    if (!delays.empty()) {
#if VIPS_VERSION_AT_LEAST(8, 9, 0)
        if (delays.size() == 1) {
            // We have just one delay, repeat that value for all frames
            delays.insert(delays.end(), query_->get<int>("n") - 1, delays[0]);
        }

        // Multiple delay values are supported, set an array of ints instead
        output_image.set("delay", delays);
#else
        // Multiple delay values are not supported, set the gif-delay field.
        // Note: this is centiseconds (the GIF standard).
        output_image.set("gif-delay", std::rint(delays[0] / 10.0));
#endif
    }

    auto output = query_->get<Output>("output", Output::Origin);
    auto image_type = query_->get<ImageType>("type", ImageType::Unknown);

    std::string out;
    std::string extension;

    if (output == Output::Json) {
        out = utils::image_to_json(output_image, image_type);
        extension = ".json";
    } else {
        if (output == Output::Origin) {
            // We force the output to PNG if the image has alpha and doesn't
            // have the right extension to output alpha (useful for masking and
            // embedding).
            if (query_->get<bool>("has_alpha", false) &&
                !utils::support_alpha_channel(image_type)) {
                output = Output::Png;
            } else {
                output = utils::to_output(image_type);
            }
        }

        extension = utils::determine_image_extension(output);

        void *buf;
        size_t size;

        // Strip all metadata (EXIF, XMP, IPTC).
        // (all savers supports this option)
        vips::VOption *save_options = VImage::option()->set("strip", true);

        append_save_options(output, save_options);

        // Write the image to a formatted string
        output_image.write_to_buffer(extension.c_str(), &buf, &size,
                                     save_options);

        out.assign(static_cast<const char *>(buf), size);

        g_free(buf);
    }

    return std::make_pair(out, extension);
}

}  // namespace processors
}  // namespace api
}  // namespace weserv
