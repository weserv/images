#include "target.h"

namespace weserv::api::io {

/* Class implementation */

// We need C linkage for this.
extern "C" {
G_DEFINE_TYPE(WeservTarget, weserv_target, VIPS_TYPE_TARGET);
}

static gint64 weserv_target_write_wrapper(VipsTarget *target, const void *data,
                                          size_t length) {
    auto *weserv_target = WESERV_TARGET(target)->target;

    return weserv_target->write(data, length);
}

// LCOV_EXCL_START
static gint64 weserv_target_read_wrapper(VipsTarget *target, void *data,
                                         size_t length) {
    auto *weserv_target = WESERV_TARGET(target)->target;

    return weserv_target->read(data, length);
}

static gint64 weserv_target_seek_wrapper(VipsTarget *target, gint64 offset,
                                         int whence) {
    auto *weserv_target = WESERV_TARGET(target)->target;

    return weserv_target->seek(offset, whence);
}
// LCOV_EXCL_STOP

static int weserv_target_end_wrapper(VipsTarget *target) {
    auto *weserv_target = WESERV_TARGET(target)->target;

    return weserv_target->end();
}

static void weserv_target_class_init(WeservTargetClass *klass) {
    GObjectClass *gobject_class = G_OBJECT_CLASS(klass);
    VipsObjectClass *object_class = VIPS_OBJECT_CLASS(klass);
    VipsTargetClass *target_class = VIPS_TARGET_CLASS(klass);

    gobject_class->set_property = vips_object_set_property;
    gobject_class->get_property = vips_object_get_property;

    object_class->nickname = "target";
    object_class->description = "weserv target";

    target_class->write = weserv_target_write_wrapper;
    target_class->read = weserv_target_read_wrapper;
    target_class->seek = weserv_target_seek_wrapper;
    target_class->end = weserv_target_end_wrapper;

    // clang-format off
    VIPS_ARG_POINTER(klass, "target", 3,
                     "Target pointer",
                     "Pointer to target",
                     VIPS_ARGUMENT_REQUIRED_INPUT,
                     G_STRUCT_OFFSET(WeservTarget, target));
    // clang-format on
}

static void weserv_target_init(WeservTarget *target) {}

/* private API */

Target Target::new_to_pointer(const std::unique_ptr<TargetInterface> &target) {
    WeservTarget *weserv_target = WESERV_TARGET(
        g_object_new(WESERV_TYPE_TARGET, "target", target.get(), nullptr));

    if (vips_object_build(VIPS_OBJECT(weserv_target)) != 0) {
        VIPS_UNREF(weserv_target);  // LCOV_EXCL_START
        throw vips::VError();
        // LCOV_EXCL_STOP
    }

    return Target(weserv_target);
}

Target Target::new_to_file(const std::string &filename) {
    VipsTarget *target = vips_target_new_to_file(filename.c_str());

    if (target == nullptr) {
        throw vips::VError();  // LCOV_EXCL_LINE
    }

    return Target(target);
}

Target Target::new_to_memory() {
    VipsTarget *target = vips_target_new_to_memory();

    if (target == nullptr) {
        throw vips::VError();  // LCOV_EXCL_LINE
    }

    return Target(target);
}

void Target::setup(const std::string &extension) const {
    VipsTarget *output = get_target();
    if (WESERV_IS_TARGET(output)) {
        TargetInterface *target = WESERV_TARGET(output)->target;
        target->setup(extension);
    }
}

int64_t Target::write(const void *data, size_t length) const {
    return vips_target_write(get_target(), data, length);
}

int Target::end() const {
    return vips_target_end(get_target());
}

}  // namespace weserv::api::io
