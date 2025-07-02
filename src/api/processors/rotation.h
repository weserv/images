#pragma once

#include "base.h"

namespace weserv::api::processors {

class Rotation : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;
};

}  // namespace weserv::api::processors
