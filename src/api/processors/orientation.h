#pragma once

#include "processors/base.h"

namespace weserv {
namespace api {
namespace processors {

class Orientation : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
