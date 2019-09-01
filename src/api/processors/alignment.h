#pragma once

#include "processors/base.h"

#include <algorithm>

namespace weserv {
namespace api {
namespace processors {

class Alignment : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
