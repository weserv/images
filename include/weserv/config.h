#pragma once

#include <cstdint>
#include <ctime>
#include <weserv/enums.h>

namespace weserv::api {

/**
 * Data structure that can be configured within the weserv module.
 */
struct Config {
    explicit Config()
        : savers(static_cast<uintptr_t>(enums::Output::All)),
          process_timeout(10), limit_input_pixels(71000000),
          limit_output_pixels(71000000), max_pages(256), quality(80),
          avif_quality(80), jpeg_quality(80), tiff_quality(80),
          webp_quality(80), avif_effort(4), gif_effort(7), webp_effort(4),
          zlib_level(6), fail_on_error(0) {}

    /**
     * Enables or disables image savers to be used within the `&output=` query
     * parameter.
     * All supported savers are enabled by default.
     */
    uintptr_t savers;

    /**
     * Specifies a maximum allowed time for image processing.
     * Defaults to `10s`, set to `0` to remove the limit.
     * weserv_process_timeout 10s;
     */
    time_t process_timeout;

    /**
     * Do not process input images where the number of pixels (width x height)
     * exceeds this limit. Assumes image dimensions contained in the input
     * metadata can be trusted.
     * Defaults to `71000000`, set to `0` to remove the limit.
     * weserv_limit_input_pixels 71000000;
     */
    uintptr_t limit_input_pixels;

    /**
     * The same as weserv_limit_input_pixels, but for output images, after any
     * upscaling.
     * Defaults to `71000000`, set to `0` to remove the limit.
     * weserv_limit_output_pixels 71000000;
     */
    uintptr_t limit_output_pixels;

    /**
     * The maximum number of pages to extract for multi-page input (GIF, TIFF,
     * PDF, WebP).
     * Defaults to 256 pages, which should be plenty.
     * weserv_max_pages 256;
     */
    intptr_t max_pages;

    /**
     * The default quality to use for JPEG, WebP, TIFF and AVIF images.
     * Defaults to `80` which usually produces excellent results.
     * NOTE: Can be overridden with `&q=`.
     * weserv_quality 80;
     */
    intptr_t quality;

    /**
     * The default quality to use for AVIF images.
     * NOTE: Can be overridden with `&q=`.
     * weserv_avif_quality 50;
     */
    intptr_t avif_quality;

    /**
     * The default quality to use for JPEG images.
     * NOTE: Can be overridden with `&q=`.
     * weserv_jpeg_quality 80;
     */
    intptr_t jpeg_quality;

    /**
     * The default quality to use for TIFF images.
     * NOTE: Can be overridden with `&q=`.
     * weserv_tiff_quality 80;
     */
    intptr_t tiff_quality;

    /**
     * The default quality to use for WebP images.
     * NOTE: Can be overridden with `&q=`.
     * weserv_webp_quality 80;
     */
    intptr_t webp_quality;

    /**
     * Controls the CPU effort spent on improving AVIF compression.
     * Defaults to 4.
     * weserv_avif_effort 4;
     */
    intptr_t avif_effort;

    /**
     * Controls the CPU effort spent on improving GIF compression.
     * Defaults to 7.
     * weserv_gif_effort 7;
     */
    intptr_t gif_effort;

    /**
     * Controls the CPU effort spent on improving WebP compression.
     * Defaults to 4.
     * weserv_webp_effort 4;
     */
    intptr_t webp_effort;

    /**
     * zlib compression level, 0-9.
     * Defaults to 6 (`Z_DEFAULT_COMPRESSION`) which is intended to be a good
     * compromise between speed and compression effectiveness.
     * NOTE: Can be overridden `&l=`.
     * weserv_zlib_level 6;
     */
    intptr_t zlib_level;

    /**
     * Do a "best effort" to decode images, even if the data is corrupt or
     * invalid. Set this flag to `on` if you would rather to halt processing and
     * raise an error when loading invalid images. See: CVE-2019-6976
     * https://blog.silentsignal.eu/2019/04/18/drop-by-drop-bleeding-through-libvips/
     * https://github.com/weserv/images/issues/194
     * Defaults to `off`.
     * weserv_fail_on_error off;
     */
    intptr_t fail_on_error;
};

}  // namespace weserv::api
