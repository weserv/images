#pragma once

#include "exceptions/invalid.h"
#include "exceptions/large.h"
#include "exceptions/unreadable.h"
#include "processors/base.h"

#include <algorithm>
#include <string>
#include <utility>
#include <vector>

namespace weserv {
namespace api {
namespace processors {

class ImageBuffer {
 public:
    explicit ImageBuffer(parsers::QueryHolderPtr query)
        : query_(std::move(query)) {}

    VImage from_buffer(const std::string &buf) const;

    std::pair<std::string, std::string> to_buffer(const VImage &image) const;

 private:
    /**
     * Query holder.
     */
    const parsers::QueryHolderPtr query_;

    /**
     * Finds the largest/smallest page in the range [0, VIPS_META_N_PAGES].
     * Pages are compared using the given comparison function.
     * See: https://github.com/weserv/images/issues/170.
     * @param buf Image buffer.
     * @param loader Image loader.
     * @param comp Comparison function object.
     * @return The largest/smallest page in the range [0, VIPS_META_N_PAGES].
     */
    template <typename Comparator>
    int resolve_page(const std::string &buf, const std::string &loader,
                     Comparator comp) const;

    /**
     * Get the page options for a specified loader to pass on
     * to the load operation.
     * @param buf Image buffer.
     * @param loader Image loader.
     * @return Any options to pass on to the load operation
     */
    std::pair<int, int> get_page_load_options(const std::string &buf,
                                              const std::string &loader) const;

    /**
     * Load a formatted image from memory.
     * @note This behaves exactly as `VImage::new_from_buffer`, but the loader
     *       can be specified instead of being found automatically.
     *       It will throw a `UnreadableImageException` if an error occurs
     *       during loading.
     * @param buf Image buffer.
     * @param loader Image loader.
     * @param options Any options to pass on to the load operation.
     * @return A new `VImage`.
     */
    VImage new_from_buffer(const std::string &buf, const std::string &loader,
                           vips::VOption *options) const;

    /**
     * Resolve dimensions (width, height).
     */
    void resolve_dimensions() const;

    /**
     * Resolve the angle of rotation and need-to-flip
     * for the given exif orientation and query parameters
     * @param image The source image.
     */
    void resolve_rotation_and_flip(const VImage &image) const;

    /**
     * Append the save options for a specified image output.
     * These options will be passed on to the selected save operation.
     * @tparam Output Image output.
     * @param options Options to pass on to the selected save operation.
     */
    template <enums::Output Output>
    void append_save_options(vips::VOption *options) const;

    /**
     * Append the save options for a specified image output.
     * These options will be passed on to the selected save operation.
     * @param output Image output.
     * @param options Options to pass on to the selected save operation.
     */
    void append_save_options(const enums::Output &output,
                             vips::VOption *options) const;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
