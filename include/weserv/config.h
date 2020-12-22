#pragma once

#include <cstddef>
#include <weserv/enums.h>

namespace weserv {
namespace api {

/**
 * Data structure that can be configured within the weserv module.
 */
struct Config {
    explicit Config()
        : savers(static_cast<uintptr_t>(enums::Output::All)),
          limit_input_pixels(71000000), limit_output_pixels(71000000),
          max_pages(256), default_quality(85), default_level(6),
          fail_on_error(0) {}

    // Enables or disables image savers to be used within the `&output=` query
    // parameter.
    // All supported savers are enabled by default.
    uintptr_t savers;

    // Do not process input images where the number of pixels (width x height)
    // exceeds this limit. Assumes image dimensions contained in the input
    // metadata can be trusted.
    // Defaults to `71000000`, set to `0` to remove the limit.
    // weserv_limit_input_pixels 71000000;
    uintptr_t limit_input_pixels;

    // The same as weserv_limit_input_pixels, but for output images, after any
    // upscaling.
    // Defaults to `71000000`, set to `0` to remove the limit.
    // weserv_limit_output_pixels 71000000;
    uintptr_t limit_output_pixels;

    // The maximum number of pages to extract for multi-page input (GIF, TIFF,
    // PDF, WebP).
    // Defaults to 256 pages, which should be plenty.
    // weserv_max_pages 256;
    intptr_t max_pages;

    // The default quality to use for JPEG, WebP and TIFF images.
    // Defaults to `85` which usually produces excellent results.
    // NOTE: Can be overridden with `&q=`.
    // weserv_default_quality 85;
    intptr_t default_quality;

    // zlib compression level, 0-9.
    // Defaults to 6 (`Z_DEFAULT_COMPRESSION`) which is intended to be a good
    // compromise between speed and compression effectiveness.
    // NOTE: Can be overridden `&l=`.
    // weserv_default_level 6;
    intptr_t default_level;

    // Do a "best effort" to decode images, even if the data is corrupt or
    // invalid. Set this flag to `on` if you would rather to halt processing and
    // raise an error when loading invalid images. See: CVE-2019-6976
    // https://blog.silentsignal.eu/2019/04/18/drop-by-drop-bleeding-through-libvips/
    // https://github.com/weserv/images/issues/194
    // Defaults to `off`.
    // weserv_fail_on_error off;
    intptr_t fail_on_error;
};

}  // namespace api
}  // namespace weserv
