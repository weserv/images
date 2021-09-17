#pragma once

#include "base.h"

namespace weserv {
namespace api {
namespace processors {

class Orientation : ImageProcessor {
 public:
    Orientation(std::shared_ptr<parsers::Query> query, const Config &config)
        : ImageProcessor(std::move(query)), config_(config) {}

    VImage process(const VImage &image) const override;

 private:
    /**
     * Global config.
     */
    const Config &config_;
};

}  // namespace processors
}  // namespace api
}  // namespace weserv
