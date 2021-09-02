#pragma once

#include <cstddef>
#include <cstdint>
#include <string>

namespace weserv {
namespace api {
namespace io {

class TargetInterface {
 public:
    virtual ~TargetInterface() = default;

    /**
     * Emitted just before an image is being written to a target.
     * It's a good place to open a file descriptor or to set up the response's
     * `Content-Type` header.
     * @param extension Extension of the image to be written.
     */
    virtual void setup(const std::string &extension) = 0;

    /**
     * Write to output, args exactly as write(2).
     * @param data Input buffer.
     * @param length Number of bytes to write
     * @return Number of bytes written or -1 on error, 0 on EOF.
     */
    virtual int64_t write(const void *data, size_t length) = 0;

    /**
     * Output has been generated, so do any clearing up, e.g. close a file
     * descriptor or copy the bytes we saved in memory to the target blob.
     */
    virtual void finish() = 0;
};

}  // namespace io
}  // namespace api
}  // namespace weserv
