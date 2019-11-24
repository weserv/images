#include <weserv/utils/status.h>

#include <sstream>
#include <utility>

namespace weserv {
namespace api {
namespace utils {

Status::Status(int code, std::string message, ErrorCause error_cause)
    : code_(code), message_(std::move(message)), error_cause_(error_cause) {}

Status::Status(int code, const std::string &message)
    : Status(code, message, ErrorCause::Internal) {}

Status::Status() : Status(200, "", ErrorCause::Internal) {}

Status::Status(Status::Code code, std::string message,
               Status::ErrorCause error_cause)
    : Status(static_cast<int>(code), std::move(message), error_cause) {}

bool Status::operator==(const Status &x) const {
    return !(code_ != x.code_ || message_ != x.message_ ||
             error_cause_ != x.error_cause_);
}

const Status &Status::OK = Status();

int Status::http_code() const {
    if (code_ == 200) {
        return code_;
    }

    if (error_cause_ == ErrorCause::Internal) {
        // Map NGINX status codes to HTTP status codes
        switch (code_) {
            case -1:
                // ERROR -> INTERNAL_SERVER_ERROR
                return 500;
            case -2:
                // AGAIN -> CONTINUE
                return 100;
            case -3:
                // BUSY -> TOO MANY REQUESTS
                return 429;
            case -4:
                // DONE -> ACCEPTED
                return 202;
            case -5:
                // DECLINED -> NOT FOUND
                return 404;
            case -6:
                // ABORT -> BAD REQUEST
                return 400;
            default:
                // UNKNOWN -> INTERNAL_SERVER_ERROR
                return 500;
        }
    }

    if (error_cause_ == ErrorCause::Upstream) {
        // If the code is an upstream HTTP error code, just return 404.
        // (because the image could not be found)
        return 404;
    }

    // if (error_cause_ == ErrorCause::APPLICATION)

    // If the code is an application error code, remap it
    switch (static_cast<Code>(code_)) {
        case Code::Ok:
            return 200;
        case Code::InvalidImage:
        case Code::ImageNotReadable:
        case Code::ImageTooLarge:
            return 404;
        case Code::InvalidUri:
        case Code::LibvipsError:
            return 400;
        case Code::Unknown:
        default:
            return 500;
    }
}

std::string Status::to_json() const {
    std::ostringstream http_out;

    if (code_ == 200 || code_ == 0) {
        http_out << "{";
        http_out << R"("status":"success",)";
        http_out << "\"code\":" << code_ << ",";
        http_out << R"("message":"OK")";
        http_out << "}";
    } else if (code_ == 500) {
        http_out << "{";
        http_out << R"("status":"error",)";
        http_out << "\"code\":" << code_ << ",";
        http_out << R"("message":"Something's wrong! )";
        http_out << "It looks as though we've broken something on our system. ";
        http_out
            << "Don't panic, we are fixing it! Please come back in a while..";
        http_out << "\"}";
    } else {
        http_out << "{";
        http_out << R"("status":"error",)";
        http_out << "\"code\":" << http_code() << ",";
        http_out << R"("message":")";

        switch (error_cause_) {
            case ErrorCause::Internal:
                http_out << "NGINX returned error: " << code_;
                if (!message_.empty()) {
                    http_out << " (message: " << message_ << ")";
                }
                break;
            case ErrorCause::Upstream:
                if (code_ == 408 || code_ == 504) {
                    // Request or gateway timeout
                    http_out << "The requested URL timed out.";
                } else if (code_ == 502) {
                    // DNS unresolvable or blocked by policy
                    http_out << "The hostname of the origin is unresolvable "
                                "(DNS) or blocked by policy.";
                } else if ((code_ == 310 || code_ == 413) &&
                           !message_.empty()) {
                    http_out << message_;
                } else {
                    http_out << "The requested URL returned error: " << code_;
                }
                break;
            case Status::ErrorCause::Application:
            default:
                if (!message_.empty()) {
                    http_out << message_;
                } else {
                    http_out << "Error code: " << code_;
                }
                break;
        }

        http_out << "\"}";
    }

    return http_out.str();
}

}  // namespace utils
}  // namespace api
}  // namespace weserv
