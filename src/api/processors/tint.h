#pragma once

#include "base.h"

namespace weserv::api::processors {

class Tint : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;
};

}  // namespace weserv::api::processors
