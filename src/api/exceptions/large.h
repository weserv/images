#pragma once

#include <stdexcept>

namespace weserv::api::exceptions {

/**
 * Exception when a provided image is too large for processing.
 */
class TooLargeImageException : public std::runtime_error {
 public:
    explicit TooLargeImageException(const std::string &error)
        : std::runtime_error(error) {}
};

}  // namespace weserv::api::exceptions
