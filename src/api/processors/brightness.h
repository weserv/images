#pragma once

#include "base.h"

namespace weserv {
namespace api {
namespace processors {

class Brightness : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    VImage process(const VImage &image) const override;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
