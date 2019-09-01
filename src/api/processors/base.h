#pragma once

#include "enums.h"
#include "parsers/query.h"
#include "utils/utility.h"

#include <utility>

#include <vips/vips8>

namespace weserv {
namespace api {
namespace processors {

using vips::VImage;

class ImageProcessor {
 public:
    explicit ImageProcessor(parsers::QueryHolderPtr query)
        : query_(std::move(query)) {}

    virtual VImage process(const VImage &image) const = 0;

    template <typename Processor>
    friend VImage operator|(const VImage &image, const Processor &processor) {
        return processor.process(image);
    }

 protected:
    /**
     * Query holder.
     */
    const parsers::QueryHolderPtr query_;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
