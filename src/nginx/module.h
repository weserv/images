#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/api_manager.h>

#include "http.h"

#include <map>
#include <memory>
#include <string>

#define NGX_WESERV_IMAGE_BUFFERED 0x08

#define NGX_WESERV_PROXY_MODE 0
#define NGX_WESERV_FILE_MODE 1

#define NGX_WESERV_BASE_CTX 0
#define NGX_WESERV_UPSTREAM_CTX 1
#define NGX_WESERV_FILTER_CTX 2

namespace weserv {
namespace nginx {

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

    ngx_flag_t enable;

    ngx_uint_t mode;

    ngx_str_t user_agent;

    size_t max_size;

    ngx_uint_t max_redirects;
};

/**
 * Base runtime state of the weserv module.
 */
struct ngx_weserv_base_ctx_t {
    /**
     * Make a polymorphic type
     */
    virtual ~ngx_weserv_base_ctx_t() = default;

    virtual int id() const {
        return NGX_WESERV_BASE_CTX;
    }
};

/**
 * Filter runtime state of the weserv module.
 */
struct ngx_weserv_filter_ctx_t : ngx_weserv_base_ctx_t {
    u_char *image;
    u_char *last;

    size_t length;

    int id() const override {
        return NGX_WESERV_FILTER_CTX;
    }
};

/**
 * Upstream runtime state of the weserv module.
 */
struct ngx_weserv_upstream_ctx_t : ngx_weserv_filter_ctx_t {
    /**
     * HTTP upstream subrequest connection
     */
    ngx_weserv_http_connection *http_subrequest;

    int id() const override {
        return NGX_WESERV_UPSTREAM_CTX;
    }
};

}  // namespace nginx
}  // namespace weserv

extern ngx_module_t ngx_weserv_module;
