#include "api_manager_impl.h"

namespace weserv {
namespace api {

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
        env_->log_info("Buffer contains unsupported image format. Cause: " +
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

Status ApiManagerImpl::process(const std::string &query,
                               const std::string &in_buf, std::string *out_buf,
                               std::string *out_ext) {
    auto query_holder = parsers::parse<parsers::QueryHolderPtr>(query);

    // Note: the disadvantage of pre-resize extraction behaviour is that none
    // of the very fast shrink-on-load tricks are possible. This can make
    // thumbnailing of large images extremely slow. So, turn it off by default.
    auto precrop = query_holder->get<bool>("precrop", false);

    // Image buffer processor
    auto image_buffer = processors::ImageBuffer(query_holder);

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

    std::string buf;
    std::string extension;

    try {
        // Create image from input buffer
        auto image = image_buffer.from_buffer(in_buf);

        // Image processing phase 1 (make sure trimming is done first)
        image = image | trim;

        // Image processing phase 2 (size, crop, etc.)
        if (precrop) {
            image = image | orientation | crop | thumbnail | alignment;
        } else {
            // The very fast shrink-on-load tricks are possible
            image = thumbnail.shrink_on_load(image, in_buf);
            image = image | thumbnail | orientation | alignment | crop;
        }

        // Image processing phase 3 (adjustments, effects, etc.)
        image = image | embed | rotation | brightness | contrast | gamma |
                sharpen | filter | blur | tint | background | mask;

        // Write the image to a buffer
        std::tie(buf, extension) = image_buffer.to_buffer(image);
    } catch (...) {
        // We'll pass the query string for debugging purposes.
        return exception_handler(query);
    }

    if (out_buf != nullptr) {
        *out_buf = buf;
    }

    if (out_ext != nullptr) {
        *out_ext = extension;
    }

    // Clean up libvips' per-request data and threads
    clean_up();

    return Status::OK;
}

}  // namespace api
}  // namespace weserv
