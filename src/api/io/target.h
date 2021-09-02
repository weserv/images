#pragma once

#include "../utils/utility.h"

#include <cstdio>  // for fclose, fopen, fwrite
#include <memory>
#include <string>
#include <utility>  // for move

#include <weserv/io/target_interface.h>

namespace weserv {
namespace api {
namespace io {

#if VIPS_VERSION_AT_LEAST(8, 12, 0)
struct WeservTargetClass {
    VipsTargetClass parent_class;
};

struct WeservTarget {
    VipsTarget parent_object;

    /*< private >*/
    io::TargetInterface *target;
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
extern "C" {
GType weserv_target_get_type(void);
}

class Target : public vips::VTarget {
 public:
    using VTarget::VTarget;

    explicit Target(WeservTarget *target, vips::VSteal steal = vips::STEAL)
        : VTarget(VIPS_TARGET(target), steal) {}
#else
class FileTarget : public io::TargetInterface {
 public:
    explicit FileTarget(std::string filename)
        : filename_(std::move(filename)) {}

    void setup(const std::string & /*unused*/) override {
        file_ = std::fopen(filename_.c_str(), "wb");
    }

    int64_t write(const void *data, size_t length) override {
        return static_cast<int64_t>(
            std::fwrite(data, sizeof(char), length, file_));
    }

    void finish() override {
        std::fclose(file_);
    }

 private:
    std::string filename_;
    std::FILE *file_{};
};

class MemoryTarget : public io::TargetInterface {
 public:
    explicit MemoryTarget(std::string *out_memory) : memory_(out_memory) {}

    void setup(const std::string & /*unused*/) override {}

    int64_t write(const void *data, size_t length) override {
        if (memory_ == nullptr) {
            return 0;
        }
        memory_->append(static_cast<const char *>(data), length);
        return static_cast<int64_t>(length);
    }

    void finish() override {}

 private:
    std::string *memory_;
};

class Target {
 public:
    explicit Target(std::unique_ptr<io::TargetInterface> target)
        : target_(std::move(target)) {}
#endif
    /**
     * Create a target which will output to a pointer.
     * @param target Write to this pointer.
     * @return A new Target class.
     */
    static Target new_to_pointer(std::unique_ptr<io::TargetInterface> target);

    /**
     * Create a target which will output to a file.
     * @return A new Target class.
     */
    static Target new_to_file(const std::string &filename);

#if VIPS_VERSION_AT_LEAST(8, 12, 0)
    /**
     * Create a target which will output to a memory area.
     * @return A new Target class.
     */
    static Target new_to_memory();
#else
    /**
     * Create a target which will output to a memory area.
     * @param out_memory output memory area.
     * @return A new Target class.
     */
    static Target new_to_memory(std::string *out_memory);
#endif

    void setup(const std::string &extension) const;

    int64_t write(const void *data, size_t length) const;

    void finish() const;

#if !VIPS_VERSION_AT_LEAST(8, 12, 0)
 private:
    std::unique_ptr<io::TargetInterface> target_;
#endif
};

}  // namespace io
}  // namespace api
}  // namespace weserv
