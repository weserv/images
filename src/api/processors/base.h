#pragma once

#include "enums.h"
#include "parsers/query.h"
#include "utils/utility.h"

#include <utility>

#include <vips/vips8>
#include <weserv/enums.h>

namespace weserv {
namespace api {
namespace processors {

using vips::VImage;

class ImageProcessor {
 public:
    explicit ImageProcessor(std::shared_ptr<parsers::Query> query)
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
    const std::shared_ptr<parsers::Query> query_;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
