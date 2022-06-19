#pragma once

#include <cstddef>
#include <cstdint>

namespace weserv::api::io {

class SourceInterface {
 public:
    virtual ~SourceInterface() = default;

    /**
     * Read from the source into the supplied buffer, args exactly as read(2).
     * @param data Output buffer.
     * @param length Number of bytes to read.
     * @return Number of bytes read or -1 on error, 0 on EOF.
     */
    virtual int64_t read(void *data, size_t length) = 0;

    /**
     * Seek to a certain position, args exactly as lseek(2).
     * Unseekable sources should always return -1. It will then seek by
     * read()ing bytes into memory as required.
     * @param offset Offset of the pointer.
     * @param whence Method in which the offset is to be interpreted.
     * @return Offset of the pointer or -1 on error.
     */
    virtual int64_t seek(int64_t offset, int whence) = 0;
};

}  // namespace weserv::api::io
