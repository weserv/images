#pragma once

#include <memory>
#include <string>

#include <vips/vips8>
#include <weserv/io/source_interface.h>

namespace weserv::api::io {

struct WeservSourceClass {
    VipsSourceClass parent_class;
};

struct WeservSource {
    VipsSource parent_object;

    /*< private >*/
    SourceInterface *source;
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
extern "C" GType weserv_source_get_type();

class Source : public vips::VSource {
 public:
    using VSource::VSource;

    explicit Source(WeservSource *target, vips::VSteal steal = vips::STEAL)
        : VSource(VIPS_SOURCE(target), steal) {}

    /**
     * Create a new source from a pointer.
     * @param source Read from this pointer.
     * @return A new Source class.
     */
    static Source
    new_from_pointer(const std::unique_ptr<SourceInterface> &source);

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
};

}  // namespace weserv::api::io
