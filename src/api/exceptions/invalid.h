#pragma once

#include <stdexcept>

namespace weserv {
namespace api {
namespace exceptions {

/**
 * Exception when a provided image is not valid.
 */
class InvalidImageException : public std::runtime_error {
 public:
    explicit InvalidImageException(const std::string &error)
        : std::runtime_error(error) {}
};

}  // namespace exceptions
}  // namespace api
}  // namespace weserv
