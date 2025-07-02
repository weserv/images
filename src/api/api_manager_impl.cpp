#include "api_manager_impl.h"

#include "exceptions/invalid.h"
#include "exceptions/large.h"
#include "exceptions/unreadable.h"
#include "exceptions/unsupported.h"

#include "parsers/query.h"

#include "processors/alignment.h"
#include "processors/background.h"
#include "processors/blur.h"
#include "processors/brightness.h"
#include "processors/contrast.h"
#include "processors/crop.h"
#include "processors/embed.h"
#include "processors/filter.h"
#include "processors/gamma.h"
#include "processors/mask.h"
#include "processors/modulate.h"
#include "processors/orientation.h"
#include "processors/rotation.h"
#include "processors/sharpen.h"
#include "processors/stream.h"
#include "processors/thumbnail.h"
#include "processors/tint.h"
#include "processors/trim.h"

#include "utils/utility.h"

#include <exception>
#include <utility>

#include <vips/vips8>

namespace weserv::api {

using io::Source;
using io::Target;
using utils::Status;
using vips::VError;

std::shared_ptr<ApiManager>
ApiManagerFactory::create_api_manager(std::unique_ptr<ApiEnvInterface> env) {
    return std::make_shared<ApiManagerImpl>(std::move(env));
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
    auto *env = static_cast<ApiEnvInterface *>(user_data);

    // Log libvips warnings
    env->log_warning("libvips warning: " + std::string(message));
}

ApiManagerImpl::ApiManagerImpl(std::unique_ptr<ApiEnvInterface> env)
    : env_(std::move(env)) {
    int vips_result = vips_init("weserv");
    if (vips_result == 0) {
        // Disable the libvips cache -- it won't help and will just burn memory
        vips_cache_set_max(0);

        // We limit the pipe within the nginx module
        vips_pipe_read_limit_set(-1);

        handler_id_ = g_log_set_handler("VIPS", G_LOG_LEVEL_WARNING,
                                        vips_warning_callback, env_.get());
    } else {  // LCOV_EXCL_START
        std::string error(vips_error_buffer());
        vips_error_clear();

        env_->log_error("error: Unable to start up libvips: " + error);
        // LCOV_EXCL_STOP
    }
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

        return {Status::Code::InvalidImage,
                "Invalid or unsupported image format. Is it a valid image?",
                Status::ErrorCause::Application};
    } catch (const exceptions::UnreadableImageException &e) {
        // Log image not readable errors
        env_->log_error("Image has a corrupt header. Cause: " +
                        std::string(e.what()) + "\nQuery: " + query);

        return {Status::Code::ImageNotReadable,
                "Image not readable. Is it a valid image?",
                Status::ErrorCause::Application};
    } catch (const exceptions::TooLargeImageException &e) {
        return {Status::Code::ImageTooLarge, e.what(),
                Status::ErrorCause::Application};
    } catch (const exceptions::UnsupportedSaverException &e) {
        return {Status::Code::UnsupportedSaver, e.what(),
                Status::ErrorCause::Application};
    } catch (const VError &e) {
        std::string error_str = e.what();

        // Log libvips errors
        env_->log_error("libvips error: " + error_str + "\nQuery: " + query);

        // Get the first error message, when we are in our own log domain
        if (error_str.rfind("weserv: ", 0) == 0) {
            error_str = error_str.substr(8, error_str.find('\n') - 8);
        }

        return {Status::Code::LibvipsError,
                "libvips error: " + utils::escape_string(error_str),
                Status::ErrorCause::Application};
    } catch (const std::exception &e) {  // LCOV_EXCL_START
        auto error_str = "unknown error: " + std::string(e.what());

        // Log unknown errors
        env_->log_error(error_str + "\nQuery: " + query);

        return {Status::Code::Unknown, error_str,
                Status::ErrorCause::Application};
        // LCOV_EXCL_STOP
    }
}

Status ApiManagerImpl::process(const std::string &query,
                               const Source &source,
                               const Target &target,
                               const Config &config) {
    auto query_holder = std::make_unique<parsers::Query>(query);

    // Note: the disadvantage of pre-resize extraction behaviour is that none
    // of the very fast shrink-on-load tricks are possible. This can make
    // thumbnailing of large images extremely slow. So, turn it off by default.
    auto precrop = query_holder->get<bool>("precrop", false);

    // Stream processor
    auto stream = processors::Stream(query_holder, config);

    // Image processors
    auto trim = processors::Trim(query_holder, config);
    auto thumbnail = processors::Thumbnail(query_holder, config);
    auto orientation = processors::Orientation(query_holder, config);
    auto alignment = processors::Alignment(query_holder, config);
    auto crop = processors::Crop(query_holder, config);
    auto embed = processors::Embed(query_holder, config);
    auto rotation = processors::Rotation(query_holder, config);
    auto brightness = processors::Brightness(query_holder, config);
    auto modulate = processors::Modulate(query_holder, config);
    auto contrast = processors::Contrast(query_holder, config);
    auto gamma = processors::Gamma(query_holder, config);
    auto sharpen = processors::Sharpen(query_holder, config);
    auto filter = processors::Filter(query_holder, config);
    auto blur = processors::Blur(query_holder, config);
    auto tint = processors::Tint(query_holder, config);
    auto background = processors::Background(query_holder, config);
    auto mask = processors::Mask(query_holder, config);

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
    image = image | embed | rotation | brightness | modulate | contrast |
            gamma | sharpen | filter | blur | tint | background | mask;

    // Write the image to a target
    stream.write_to_target(image, target);

    // Clean up libvips' per-request data and threads
    clean_up();

    return Status::OK;
}

Status
ApiManagerImpl::process(const std::string &query,
                        const std::unique_ptr<io::SourceInterface> &source,
                        const std::unique_ptr<io::TargetInterface> &target,
                        const Config &config) {
    try {
        return process(query, Source::new_from_pointer(source),
                       Target::new_to_pointer(target), config);
    } catch (...) {
        // We'll pass the query string for debugging purposes
        return exception_handler(query);
    }
}

Status ApiManagerImpl::process_file(const std::string &query,
                                    const std::string &in_file,
                                    const std::string &out_file,
                                    const Config &config) {
    try {
        return process(query, Source::new_from_file(in_file),
                       Target::new_to_file(out_file), config);
    } catch (...) {
        return exception_handler(query);
    }
}

Status ApiManagerImpl::process_file(const std::string &query,
                                    const std::string &in_file,
                                    std::string *out_buf,
                                    const Config &config) {
    try {
        auto target = Target::new_to_memory();
        Status status =
            process(query, Source::new_from_file(in_file), target, config);

        if (status.ok() && out_buf != nullptr) {
            size_t length;
            const void *out = vips_blob_get(target.get_target()->blob, &length);
            out_buf->assign(static_cast<const char *>(out), length);
        }

        return status;
    } catch (...) {
        return exception_handler(query);
    }
}

Status ApiManagerImpl::process_buffer(const std::string &query,
                                      const std::string &in_buf,
                                      std::string *out_buf,
                                      const Config &config) {
    try {
        auto target = Target::new_to_memory();
        Status status =
            process(query, Source::new_from_buffer(in_buf), target, config);

        if (status.ok() && out_buf != nullptr) {
            size_t length;
            const void *out = vips_blob_get(target.get_target()->blob, &length);
            out_buf->assign(static_cast<const char *>(out), length);
        }
        return status;
    } catch (...) {
        return exception_handler(query);
    }
}

}  // namespace weserv::api
