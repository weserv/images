#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/api_manager.h>
#include <weserv/config.h>

#include "http_request.h"

#include <memory>

#define NGX_WESERV_IMAGE_BUFFERED 0x08

#define NGX_WESERV_PROXY_MODE 0
#define NGX_WESERV_FILTER_MODE 1

namespace weserv::nginx {

/**
 * weserv Module Configuration - main context.
 */
struct ngx_weserv_main_conf_t {
    /**
     * The module-level API Manager interface.
     */
    std::shared_ptr<api::ApiManager> weserv;
};

/**
 * weserv Module Configuration - location context.
 */
struct ngx_weserv_loc_conf_t {
    /**
     * Upstream connection configuration, timeouts etc.
     */
    ngx_http_upstream_conf_t upstream_conf;

    /**
     * API configuration, limits etc.
     */
    api::Config api_conf;

    ngx_flag_t enable;

    ngx_uint_t mode;

    /**
     * Array of ngx_cidr_t
     */
    ngx_array_t *deny;

    ngx_str_t user_agent;

    size_t max_size;

    ngx_uint_t max_redirects;

    ngx_flag_t canonical_header;
};

/**
 * Base runtime state of the weserv module.
 */
struct ngx_weserv_base_ctx_t {
    /**
     * Make a polymorphic type.
     */
    virtual ~ngx_weserv_base_ctx_t() = default;

    /**
     * The incoming chain.
     */
    ngx_chain_t *in;
};

/**
 * Upstream runtime state of the weserv module.
 */
struct ngx_weserv_upstream_ctx_t : ngx_weserv_base_ctx_t {
    /**
     * Constructor.
     */
    ngx_weserv_upstream_ctx_t() : response_status(NGX_OK, "") {}

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

    /**
     * Chunked request body.
     */
    ngx_http_chunked_t chunked;

    /**
     * Redirect bit fields.
     */
    unsigned redirecting : 1;
    unsigned saw_temp_redirect : 1;

    /**
     * Parsed HTTP redirection URI.
     */
    ngx_str_t location;

    /**
     * Parsed HTTP canonical URI.
     * In most cases this is the same as request->url(), except when there's a
     * temporary redirect in the chain.
     */
    ngx_str_t canonical;

    /**
     * Parsed HTTP response status.
     */
    api::utils::Status response_status;

#if NGX_DEBUG
    /**
     * Debug mode.
     * 0 = disable debug (default)
     * 1 = debug outgoing request.
     * 2 = debug response headers.
     * 3 = debug response body.
     */
    unsigned debug : 2;
#endif
};

}  // namespace weserv::nginx

extern ngx_module_t ngx_weserv_module;
