#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/utils/status.h>

#include "http_request.h"

#include <memory>
#include <sstream>
#include <string>
#include <utility>

namespace weserv {
namespace nginx {

struct ngx_weserv_http_connection {
    /**
     * Constructor.
     */
    ngx_weserv_http_connection();

    /**
     * Request information.
     */

    /**
     * Target URL path and host. Host will be used to send 'Host' header.
     */
    ngx_str_t url_path;
    ngx_str_t host_header;

    /**
     * A unique pointer to the HTTP request object created by the caller
     * (contains headers, body, HTTP verb, URL, timeout, and max number of
     * redirects).
     */
    std::unique_ptr<HTTPRequest> request;

    /**
     * Response information.
     */

    ngx_http_chunked_t chunked;

    /**
     * Redirect flag.
     */
    ngx_uint_t redirecting;

    /**
     * Parsed HTTP redirection URI.
     */
    ngx_str_t location;

    /**
     * Parsed HTTP response status.
     */
    api::utils::Status response_status;
};

ngx_weserv_http_connection *get_weserv_connection(ngx_http_request_t *r);

/**
 * Sends an HTTP request.
 */
ngx_int_t
ngx_weserv_send_http_request(ngx_http_request_t *r,
                             ngx_weserv_http_connection *http_connection);

}  // namespace nginx
}  // namespace weserv
