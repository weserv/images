#pragma once

#include <memory>
#include <string>

#include <vips/vips8>
#include <weserv/io/target_interface.h>

namespace weserv::api::io {

struct WeservTargetClass {
    VipsTargetClass parent_class;
};

struct WeservTarget {
    VipsTarget parent_object;

    /*< private >*/
    TargetInterface *target;
};

#define WESERV_TYPE_TARGET (weserv_target_get_type())
#define WESERV_TARGET(obj)                                                     \
    (G_TYPE_CHECK_INSTANCE_CAST((obj), WESERV_TYPE_TARGET, WeservTarget))
#define WESERV_TARGET_CLASS(klass)                                             \
    (G_TYPE_CHECK_CLASS_CAST((klass), WESERV_TYPE_TARGET, WeservTargetClass))
#define WESERV_IS_TARGET(obj)                                                  \
    (G_TYPE_CHECK_INSTANCE_TYPE((obj), WESERV_TYPE_TARGET))
#define WESERV_IS_TARGET_CLASS(klass)                                          \
    (G_TYPE_CHECK_CLASS_TYPE((klass), WESERV_TYPE_TARGET))
#define WESERV_TARGET_GET_CLASS(obj)                                           \
    (G_TYPE_INSTANCE_GET_CLASS((obj), WESERV_TYPE_TARGET, WeservTargetClass))

// We need C linkage for this.
extern "C" GType weserv_target_get_type();

class Target : public vips::VTarget {
 public:
    using VTarget::VTarget;

    explicit Target(WeservTarget *target, vips::VSteal steal = vips::STEAL)
        : VTarget(VIPS_TARGET(target), steal) {}

    /**
     * Create a target which will output to a pointer.
     * @param target Write to this pointer.
     * @return A new Target class.
     */
    static Target
    new_to_pointer(const std::unique_ptr<TargetInterface> &target);

    /**
     * Create a target which will output to a file.
     * @return A new Target class.
     */
    static Target new_to_file(const std::string &filename);

    /**
     * Create a target which will output to a memory area.
     * @return A new Target class.
     */
    static Target new_to_memory();

    void setup(const std::string &extension) const;

    int64_t write(const void *data, size_t length) const;

    int end() const;
};

}  // namespace weserv::api::io
