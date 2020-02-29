#include "api_manager_impl.h"

namespace weserv {
namespace api {

using io::Source;
using io::Target;
using utils::Status;
using vips::VError;

std::shared_ptr<ApiManager>
ApiManagerFactory::create_api_manager(std::unique_ptr<ApiEnvInterface> env) {
    return std::shared_ptr<ApiManager>(new ApiManagerImpl(std::move(env)));
}

/**
 * Called with warnings from the glib-registered "VIPS" domain.
 * @param log_domain The log domain of the message.
 * @param log_level The log level of the message.
 * @param message The message to process.
 * @param user_data User data, set in g_log_set_handler().
 */
void vips_warning_callback(const char * /*unused*/, GLogLevelFlags /*unused*/,
                           const char *message, void *user_data) {
    auto env = static_cast<ApiEnvInterface *>(user_data);

    // Log libvips warnings
    env->log_warning("libvips warning: " + std::string(message));
}

ApiManagerImpl::ApiManagerImpl(std::unique_ptr<ApiEnvInterface> env)
    : env_(std::move(env)) {
    int vips_result = vips_init("weserv");
    if (vips_result == 0) {
        /* Disable the libvips cache -- it won't help and will just burn
         * memory.
         */
        vips_cache_set_max(0);

        handler_id_ = g_log_set_handler(
            "VIPS", static_cast<GLogLevelFlags>(G_LOG_LEVEL_WARNING),
            static_cast<GLogFunc>(vips_warning_callback), env_.get());
    } else {  // LCOV_EXCL_START
        std::string error(vips_error_buffer());
        vips_error_clear();

        env_->log_error("error: Unable to start up libvips: " + error);
    }  // LCOV_EXCL_STOP
}

ApiManagerImpl::~ApiManagerImpl() {
    if (handler_id_ > 0) {
        g_log_remove_handler("VIPS", handler_id_);
        handler_id_ = 0;
    }
}

void ApiManagerImpl::clean_up() {
    vips_error_clear();
    vips_thread_shutdown();
}

Status ApiManagerImpl::exception_handler(const std::string &query) {
    try {
        // Clean up libvips' per-request data and threads
        clean_up();
        throw;
    } catch (const exceptions::InvalidImageException &e) {
        // Log image invalid or unsupported errors
        env_->log_info("Stream contains unsupported image format. Cause: " +
                       std::string(e.what()) + "\nQuery: " + query);

        return Status(
            Status::Code::InvalidImage,
            "Invalid or unsupported image format. Is it a valid image?",
            Status::ErrorCause::Application);
    } catch (const exceptions::UnreadableImageException &e) {
        // Log image not readable errors
        env_->log_error("Image has a corrupt header. Cause: " +
                        std::string(e.what()) + "\nQuery: " + query);

        return Status(Status::Code::ImageNotReadable,
                      "Image not readable. Is it a valid image?",
                      Status::ErrorCause::Application);
    } catch (const exceptions::TooLargeImageException &e) {
        return Status(Status::Code::ImageTooLarge, e.what(),
                      Status::ErrorCause::Application);
    } catch (const VError &e) {  // LCOV_EXCL_START
        std::string error_str = e.what();

        // Log libvips errors
        env_->log_error("libvips error: " + error_str + "\nQuery: " + query);

        return Status(Status::Code::LibvipsError,
                      "libvips error: " + utils::escape_string(error_str),
                      Status::ErrorCause::Application);
    } catch (const std::exception &e) {
        auto error_str = "unknown error: " + std::string(e.what());

        // Log unknown errors
        env_->log_error(error_str + "\nQuery: " + query);

        return Status(Status::Code::Unknown, error_str,
                      Status::ErrorCause::Application);
    }
    // LCOV_EXCL_STOP
}

utils::Status ApiManagerImpl::process(const std::string &query,
                                      const Source &source,
                                      const Target &target) {
    auto query_holder = parsers::parse<parsers::QueryHolderPtr>(query);

    // Note: the disadvantage of pre-resize extraction behaviour is that none
    // of the very fast shrink-on-load tricks are possible. This can make
    // thumbnailing of large images extremely slow. So, turn it off by default.
    auto precrop = query_holder->get<bool>("precrop", false);

    // Stream processor
    auto stream = processors::Stream(query_holder);

    // Image processors
    auto trim = processors::Trim(query_holder);
    auto thumbnail = processors::Thumbnail(query_holder);
    auto orientation = processors::Orientation(query_holder);
    auto alignment = processors::Alignment(query_holder);
    auto crop = processors::Crop(query_holder);
    auto embed = processors::Embed(query_holder);
    auto rotation = processors::Rotation(query_holder);
    auto brightness = processors::Brightness(query_holder);
    auto contrast = processors::Contrast(query_holder);
    auto gamma = processors::Gamma(query_holder);
    auto sharpen = processors::Sharpen(query_holder);
    auto filter = processors::Filter(query_holder);
    auto blur = processors::Blur(query_holder);
    auto tint = processors::Tint(query_holder);
    auto background = processors::Background(query_holder);
    auto mask = processors::Mask(query_holder);

    // Create image from a source
    auto image = stream.new_from_source(source);

    // Image processing phase 1 (make sure trimming is done first)
    image = image | trim;

    // Image processing phase 2 (size, crop, etc.)
    if (precrop) {
        image = image | orientation | crop | thumbnail | alignment;
    } else {
        // The very fast shrink-on-load tricks are possible
        image = thumbnail.shrink_on_load(image, source);
        image = image | thumbnail | orientation | alignment | crop;
    }

    // Image processing phase 3 (adjustments, effects, etc.)
    image = image | embed | rotation | brightness | contrast | gamma | sharpen |
            filter | blur | tint | background | mask;

    // Write the image to a target
    stream.write_to_target(image, target);

    // Clean up libvips' per-request data and threads
    clean_up();

    return Status::OK;
}

utils::Status
ApiManagerImpl::process(const std::string &query,
                        std::unique_ptr<io::SourceInterface> source,
                        std::unique_ptr<io::TargetInterface> target) {
    try {
        return process(query, Source::new_from_pointer(std::move(source)),
                       Target::new_to_pointer(std::move(target)));
    } catch (...) {
        // We'll pass the query string for debugging purposes.
        return exception_handler(query);
    }
}

utils::Status ApiManagerImpl::process_file(const std::string &query,
                                           const std::string &in_file,
                                           const std::string &out_file) {
    try {
        return process(query, Source::new_from_file(in_file),
                       Target::new_to_file(out_file));
    } catch (...) {
        return exception_handler(query);
    }
}

utils::Status ApiManagerImpl::process_file(const std::string &query,
                                           const std::string &in_file,
                                           std::string *out_buf) {
    try {
#if VIPS_VERSION_AT_LEAST(8, 10, 0)
        auto target = Target::new_to_memory();
#else
        auto target = Target::new_to_memory(out_buf);
#endif
        Status status = process(query, Source::new_from_file(in_file), target);

#if VIPS_VERSION_AT_LEAST(8, 10, 0)
        if (status.ok() && out_buf != nullptr) {
            size_t length;
            const void *out = vips_blob_get(target.get_target()->blob, &length);
            out_buf->assign(static_cast<const char *>(out), length);
        }
#endif

        return status;
    } catch (...) {
        return exception_handler(query);
    }
}

utils::Status ApiManagerImpl::process_buffer(const std::string &query,
                                             const std::string &in_buf,
                                             std::string *out_buf) {
    try {
#if VIPS_VERSION_AT_LEAST(8, 10, 0)
        auto target = Target::new_to_memory();
#else
        auto target = Target::new_to_memory(out_buf);
#endif
        Status status = process(query, Source::new_from_buffer(in_buf), target);

#if VIPS_VERSION_AT_LEAST(8, 10, 0)
        if (status.ok() && out_buf != nullptr) {
            size_t length;
            const void *out = vips_blob_get(target.get_target()->blob, &length);
            out_buf->assign(static_cast<const char *>(out), length);
        }
#endif
        return status;
    } catch (...) {
        return exception_handler(query);
    }
}

}  // namespace api
}  // namespace weserv
