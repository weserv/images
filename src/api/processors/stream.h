#pragma once

#include "../io/blob.h"
#include "../io/source.h"
#include "../io/target.h"
#include "base.h"

#include <string>

#include <weserv/config.h>
#include <weserv/enums.h>

namespace weserv::api::processors {

class Stream {
 public:
    Stream(const std::unique_ptr<parsers::Query> &query, const Config &config)
        : query_(query), config_(config) {}

    VImage new_from_source(const io::Source &source) const;

    void write_to_target(const VImage &image, const io::Target &target) const;

 private:
    /**
     * Query holder.
     */
    const std::unique_ptr<parsers::Query> &query_;

    /**
     * Global config.
     */
    const Config &config_;

    /**
     * Finds the largest/smallest page in the range [0, n_pages].
     * Pages are compared using the given comparison function.
     * See: https://github.com/weserv/images/issues/170.
     * @tparam Comparator Comparison type.
     * @param image The source image.
     * @param n_pages Number of pages in the image.
     * @param source Source to read from.
     * @param blob (Fallback-)blob to read from.
     * @param loader Image loader.
     * @param comp Comparison function object.
     * @return The largest/smallest page in the range [0, n_pages].
     */
    template <typename Comparator>
    int resolve_page(const VImage &image, int n_pages, const io::Source &source,
                     const io::Blob &blob, const std::string &loader,
                     Comparator comp) const;

    /**
     * Get the page options to pass on to the load operation.
     * @param n_pages Number of pages in the image.
     * @return Any options to pass on to the load operation
     */
    std::pair<int, int> get_page_load_options(int n_pages) const;

    /**
     * Load a formatted image from a source.
     * @note This behaves exactly as `VImage::new_from_source`, but the loader
     *       can be specified instead of being found automatically.
     *       It will throw a `UnreadableImageException` if an error occurs
     *       during loading.
     * @param source Source to read from.
     * @param blob (Fallback-)blob to read from.
     * @param loader Image loader.
     * @param options Any options to pass on to the load operation.
     * @return A new `VImage`.
     */
    static VImage new_from_source(const io::Source &source,
                                  const io::Blob &blob,
                                  const std::string &loader,
                                  vips::VOption *options);

    /**
     * Resolve/validate the query parameters based on the given image.
     * @param image The source image.
     */
    void resolve_query(const VImage &image) const;

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

}  // namespace weserv::api::processors
