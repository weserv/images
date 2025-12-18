#pragma once

#include "base.h"

namespace weserv::api::processors {

class Watermark : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;

    // Called once at startup to limit libvips cache
    static void init_cache_limits();
};

}  // namespace weserv::api::processors
