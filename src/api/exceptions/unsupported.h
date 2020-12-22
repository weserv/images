#pragma once

#include <stdexcept>

namespace weserv {
namespace api {
namespace exceptions {

/**
 * Exception when a provided saver is not supported.
 */
class UnsupportedSaverException : public std::runtime_error {
 public:
    explicit UnsupportedSaverException(const std::string &error)
        : std::runtime_error(error) {}
};

}  // namespace exceptions
}  // namespace api
}  // namespace weserv
