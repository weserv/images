#pragma once

#include <map>
#include <string>

namespace weserv::nginx {

/**
 * Represents a non-streaming HTTP GET-request.
 */
class HTTPRequest {
 public:
    /**
     * HTTPRequest constructor.
     */
    HTTPRequest() = default;

    /**
     * Request properties.
     */
    const ngx_str_t &url() const {
        return url_;
    }

    HTTPRequest &set_url(const ngx_str_t &value) {
        url_ = value;
        return *this;
    }

    const std::map<std::string, ngx_str_t> &request_headers() const {
        return headers_;
    }

    HTTPRequest &set_header(const std::string &name, const ngx_str_t &value) {
        headers_[name] = value;
        return *this;
    }

    ngx_uint_t max_redirects() const {
        return max_redirects_;
    }

    HTTPRequest &set_max_redirects(ngx_uint_t value) {
        max_redirects_ = value;
        return *this;
    }

    ngx_uint_t redirect_count() const {
        return redirect_count_;
    }

    HTTPRequest &operator++()  // ++A
    {
        redirect_count_++;
        return *this;
    }

    HTTPRequest operator++(int)  // A++
    {
        HTTPRequest result(*this);
        ++(*this);
        return result;
    }

 private:
    /**
     * Target URI.
     */
    ngx_str_t url_{};

    /**
     * Request headers.
     */
    std::map<std::string, ngx_str_t> headers_;

    /**
     * Maximum number of redirects.
     */
    ngx_uint_t max_redirects_ = 0;

    /**
     * The number of HTTP redirects made on its current connection.
     */
    ngx_uint_t redirect_count_ = 0;
};

}  // namespace weserv::nginx
