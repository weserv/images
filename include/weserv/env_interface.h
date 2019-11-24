#pragma once

#include <string>

namespace weserv {
namespace api {

/**
 * An interface for the API to access its environment.
 */
class ApiEnvInterface {
 public:
    virtual ~ApiEnvInterface() = default;

    enum class LogLevel { Debug, Info, Warning, Error };

    void log_debug(const std::string &str) {
        log_debug(str.c_str());
    }

    void log_info(const std::string &str) {
        log_info(str.c_str());
    }

    void log_warning(const std::string &str) {
        log_warning(str.c_str());
    }

    void log_error(const std::string &str) {
        log_error(str.c_str());
    }

    void log_debug(const char *message) {
        log(LogLevel::Debug, message);
    }

    void log_info(const char *message) {
        log(LogLevel::Info, message);
    }

    void log_warning(const char *message) {
        log(LogLevel::Warning, message);
    }

    void log_error(const char *message) {
        log(LogLevel::Error, message);
    }

    virtual void log(LogLevel level, const char *message) = 0;
};

}  // namespace api
}  // namespace weserv
