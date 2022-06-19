#pragma once

#include <stdexcept>

namespace weserv::api::exceptions {

/**
 * Exception when a provided saver is not supported.
 */
class UnsupportedSaverException : public std::runtime_error {
 public:
    explicit UnsupportedSaverException(const std::string &error)
        : std::runtime_error(error) {}
};

}  // namespace weserv::api::exceptions
