#pragma once

#include <weserv/io/source_interface.h>

#include <memory>
#include <string>
#include <vips/vips8>

#include "utils/utility.h"

#if !VIPS_VERSION_AT_LEAST(8, 11, 0)
#include "exceptions/unreadable.h"
#include <fstream>
#include <utility>
#endif

namespace weserv {
namespace api {
namespace io {

#if VIPS_VERSION_AT_LEAST(8, 11, 0)
struct WeservSourceClass {
    VipsSourceClass parent_class;
};

struct WeservSource {
    VipsSource parent_object;

    /*< private >*/
    io::SourceInterface *source;
};

#define WESERV_TYPE_SOURCE (weserv_source_get_type())
#define WESERV_SOURCE(obj)                                                     \
    (G_TYPE_CHECK_INSTANCE_CAST((obj), WESERV_TYPE_SOURCE, WeservSource))
#define WESERV_SOURCE_CLASS(klass)                                             \
    (G_TYPE_CHECK_CLASS_CAST((klass), WESERV_TYPE_SOURCE, WeservSourceClass))
#define WESERV_IS_STREAM_INPUT(obj)                                            \
    (G_TYPE_CHECK_INSTANCE_TYPE((obj), WESERV_TYPE_SOURCE))
#define WESERV_IS_STREAM_INPUT_CLASS(klass)                                    \
    (G_TYPE_CHECK_CLASS_TYPE((klass), WESERV_TYPE_SOURCE))
#define WESERV_SOURCE_GET_CLASS(obj)                                           \
    (G_TYPE_INSTANCE_GET_CLASS((obj), WESERV_TYPE_SOURCE, WeservSourceClass))

// We need C linkage for this.
extern "C" {
GType weserv_source_get_type();
}

class Source : public vips::VSource {
 public:
    using VSource::VSource;

    explicit Source(WeservSource *target, vips::VSteal steal = vips::STEAL)
        : VSource(VIPS_SOURCE(target), steal) {}

#else
class Source {
 public:
    explicit Source(std::string buffer) : buffer_(std::move(buffer)) {}
#endif

    /**
     * Create a new source from a pointer.
     * @param target Read from this pointer.
     * @return A new Source class.
     */
    static Source new_from_pointer(std::unique_ptr<io::SourceInterface> source);

    /**
     * Create a source attached to a file.
     * @param filename Read from this file.
     * @return A new Source class.
     */
    static Source new_from_file(const std::string &filename);

    /**
     * Create a source attached to an area of memory.
     * @param buffer Memory area to load.
     * @return A new Source class.
     */
    static Source new_from_buffer(const std::string &buffer);

#if !VIPS_VERSION_AT_LEAST(8, 11, 0)
    /**
     * @return the buffer held by this source.
     */
    const std::string &buffer() const {
        return buffer_;
    }

 private:
    std::string buffer_;
#endif
};

}  // namespace io
}  // namespace api
}  // namespace weserv
