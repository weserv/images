#pragma once

#include <string>

namespace weserv {
namespace api {
namespace utils {

/**
 * A Status object can be used to represent an error or an OK state. Error
 * status messages have an error code, an error message, and an error cause.
 * An OK status has a code of 0 or 200 and no message.
 */
class Status final {
 public:
    enum class ErrorCause {
        Internal = 0,     // Internal proxy error (default)
        Upstream = 1,     // Upstream error
        Application = 2,  // Application error
    };

    // Application status codes
    enum class Code {
        Ok = 0,
        InvalidUri = 1,
        InvalidImage = 2,
        ImageNotReadable = 3,
        ImageTooLarge = 4,
        LibvipsError = 5,
        Unknown = 6,
    };

    /**
     * Constructs a status with an error code and message. If code == 0
     * message is ignored and a Status object identical to Status::OK
     * is constructed. Error cause is optional and defaults to
     * ErrorCause::Internal.
     */
    Status(int code, const std::string &message);

    Status(int code, std::string message, ErrorCause error_cause);

    Status(Code code, std::string message, ErrorCause error_cause);

    ~Status() = default;

    bool operator==(const Status &x) const;

    bool operator!=(const Status &x) const {
        return !operator==(x);
    }

    /**
     * Pre-defined OK status.
     */
    static const Status &OK;

    /**
     * @return true if this status is not an error
     */
    bool ok() const {
        return code_ == 200 || code_ == 0;
    }

    /**
     * @return the error code held by this status.
     */
    int code() const {
        return code_;
    }

    /**
     * @return the error message held by this status.
     */
    const std::string &message() const {
        return message_;
    }

    /**
     * @return the error cause held by this status.
     */
    ErrorCause error_cause() const {
        return error_cause_;
    }

    /**
     * @return the error code mapped to HTTP status codes.
     */
    int http_code() const;

    /**
     * @return a JSON representation of the error as a canonical status.
     */
    std::string to_json() const;

 private:
    /**
     * Constructs the OK status.
     */
    Status();

    /**
     * Error code. Zero means OK. Negative numbers are for control
     * statuses (e.g. DECLINED). Positive numbers 100 and greater
     * represent HTTP status codes.
     */
    int code_;

    /**
     * The error message if this Status represents an error, otherwise an empty
     * string if this is the OK status.
     */
    std::string message_;

    /**
     * Error cause indicating the origin of the error.
     */
    ErrorCause error_cause_;
};

}  // namespace utils
}  // namespace api
}  // namespace weserv
