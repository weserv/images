#pragma once

#include "../parsers/query.h"

#include <memory>

#include <vips/vips8>
#include <weserv/config.h>

namespace weserv::api::processors {

using vips::VImage;

class ImageProcessor {
 public:
    ImageProcessor(const std::unique_ptr<parsers::Query> &query,
                   const Config &config)
        : query_(query), config_(config) {}

    virtual VImage process(const VImage &image) const = 0;

    template <typename Processor>
    friend VImage operator|(const VImage &image, const Processor &processor) {
        return processor.process(image);
    }

 protected:
    /**
     * Query holder.
     */
    const std::unique_ptr<parsers::Query> &query_;

    /**
     * Global config.
     */
    const Config &config_;
};

}  // namespace weserv::api::processors
