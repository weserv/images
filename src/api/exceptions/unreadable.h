#pragma once

#include <stdexcept>

namespace weserv {
namespace api {
namespace exceptions {

/**
 * Exception when a provided image is not readable.
 */
class UnreadableImageException : public std::runtime_error {
 public:
    explicit UnreadableImageException(const std::string &error)
        : std::runtime_error(error) {}
};

}  // namespace exceptions
}  // namespace api
}  // namespace weserv
