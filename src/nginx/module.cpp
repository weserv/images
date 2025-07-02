#include "module.h"

#include "alloc.h"
#include "environment.h"
#include "error.h"
#include "handler.h"
#include "stream.h"
#include "util.h"

#include <weserv/enums.h>

using weserv::api::enums::Output;
using weserv::api::utils::Status;

namespace weserv::nginx {

namespace {
/**
 * The module's location callback directives.
 */
char *ngx_weserv(ngx_conf_t *cf, ngx_command_t *cmd, void *conf);
char *ngx_weserv_deny_ip(ngx_conf_t *cf, ngx_command_t *cmd, void *conf);

/**
 * Configuration - function declarations.
 */

/**
 * Creates the module's main context configuration structure.
 */
void *ngx_weserv_create_main_conf(ngx_conf_t *cf);

/**
 * Creates the module's location context configuration structure.
 */
void *ngx_weserv_create_loc_conf(ngx_conf_t *cf);
char *ngx_weserv_merge_loc_conf(ngx_conf_t *cf, void *parent, void *child);

/**
 * Pre-configuration initialization.
 */
ngx_int_t ngx_weserv_preconfiguration(ngx_conf_t *cf);

/**
 * Post-configuration initialization.
 */
ngx_int_t ngx_weserv_postconfiguration(ngx_conf_t *cf);

/**
 * Variables.
 */
ngx_int_t ngx_weserv_response_length_variable(ngx_http_request_t *r,
                                              ngx_http_variable_value_t *v,
                                              uintptr_t data);

ngx_http_output_header_filter_pt ngx_http_next_header_filter;
ngx_http_output_body_filter_pt ngx_http_next_body_filter;

ngx_conf_enum_t ngx_weserv_mode[] = {
    {ngx_string("proxy"), NGX_WESERV_PROXY_MODE},
    {ngx_string("filter"), NGX_WESERV_FILTER_MODE},
    {ngx_null_string, 0}  // last entry
};

ngx_conf_bitmask_t ngx_weserv_savers[] = {
    {ngx_string("jpg"), static_cast<ngx_uint_t>(Output::Jpeg)},
    {ngx_string("png"), static_cast<ngx_uint_t>(Output::Png)},
    {ngx_string("webp"), static_cast<ngx_uint_t>(Output::Webp)},
    {ngx_string("avif"), static_cast<ngx_uint_t>(Output::Avif)},
    {ngx_string("tiff"), static_cast<ngx_uint_t>(Output::Tiff)},
    {ngx_string("gif"), static_cast<ngx_uint_t>(Output::Gif)},
    {ngx_string("json"), static_cast<ngx_uint_t>(Output::Json)},
    {ngx_null_string, 0}  // last entry
};

ngx_conf_num_bounds_t ngx_weserv_quality_bounds = {
    ngx_conf_check_num_bounds, 1, 100
};

ngx_conf_num_bounds_t ngx_weserv_avif_effort_bounds = {
    ngx_conf_check_num_bounds, 0, 9
};

ngx_conf_num_bounds_t ngx_weserv_gif_effort_bounds = {
    ngx_conf_check_num_bounds, 1, 10
};

ngx_conf_num_bounds_t ngx_weserv_webp_effort_bounds = {
    ngx_conf_check_num_bounds, 0, 6
};

ngx_conf_num_bounds_t ngx_weserv_zlib_level_bounds = {
    ngx_conf_check_num_bounds, 0, 9
};


// clang-format off
/**
 * The module commands contain list of configurable properties for this module.
 */
ngx_command_t ngx_weserv_commands[] = {
    {ngx_string("weserv"),
     NGX_HTTP_LOC_CONF | NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_weserv,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, mode),
     &ngx_weserv_mode},

    {ngx_string("weserv_deny_ip"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_weserv_deny_ip,
     NGX_HTTP_LOC_CONF_OFFSET,
     0,
     nullptr},

    {ngx_string("weserv_connect_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.connect_timeout),
     nullptr},

    {ngx_string("weserv_send_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.send_timeout),
     nullptr},

    {ngx_string("weserv_read_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.read_timeout),
     nullptr},

    {ngx_string("weserv_user_agent"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_str_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, user_agent),
     nullptr},

    {ngx_string("weserv_max_size"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_size_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, max_size),
     nullptr},

    {ngx_string("weserv_max_redirects"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, max_redirects),
     nullptr},

    {ngx_string("weserv_canonical_header"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_FLAG,
     ngx_conf_set_flag_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, canonical_header),
     nullptr},

    {ngx_string("weserv_savers"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_1MORE,
     ngx_conf_set_bitmask_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.savers),
     &ngx_weserv_savers},

    {ngx_string("weserv_process_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_sec_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.process_timeout),
     nullptr},

    {ngx_string("weserv_max_pages"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.max_pages),
     nullptr},

    {ngx_string("weserv_limit_input_pixels"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.limit_input_pixels),
     nullptr},

    {ngx_string("weserv_limit_output_pixels"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.limit_output_pixels),
      nullptr},

    {ngx_string("weserv_quality"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.quality),
     &ngx_weserv_quality_bounds},

    {ngx_string("weserv_avif_quality"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.avif_quality),
     &ngx_weserv_quality_bounds},

    {ngx_string("weserv_jpeg_quality"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.jpeg_quality),
     &ngx_weserv_quality_bounds},

    {ngx_string("weserv_tiff_quality"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.tiff_quality),
     &ngx_weserv_quality_bounds},

    {ngx_string("weserv_webp_quality"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.webp_quality),
     &ngx_weserv_quality_bounds},

    {ngx_string("weserv_avif_effort"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.avif_effort),
     &ngx_weserv_avif_effort_bounds},

    {ngx_string("weserv_gif_effort"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.gif_effort),
     &ngx_weserv_gif_effort_bounds},

    {ngx_string("weserv_webp_effort"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.webp_effort),
     &ngx_weserv_webp_effort_bounds},

    {ngx_string("weserv_zlib_level"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_num_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.zlib_level),
     &ngx_weserv_zlib_level_bounds},

    {ngx_string("weserv_fail_on_error"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_HTTP_LIF_CONF | NGX_CONF_FLAG,
     ngx_conf_set_flag_slot,
     NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, api_conf.fail_on_error),
     nullptr},

    ngx_null_command  // last entry
};

ngx_http_variable_t ngx_weserv_vars[] = {

    {ngx_string("weserv_response_length"), nullptr,
     ngx_weserv_response_length_variable, 0,
     NGX_HTTP_VAR_NOCACHEABLE, 0},

    ngx_http_null_variable  // last entry
};
// clang-format on

ngx_int_t ngx_weserv_response_length_variable(ngx_http_request_t *r,
                                              ngx_http_variable_value_t *v,
                                              uintptr_t data) {
    v->valid = 1;
    v->no_cacheable = 0;
    v->not_found = 0;

    auto *ctx = static_cast<ngx_weserv_base_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr || r->upstream_states == nullptr ||
        r->upstream_states->nelts == 0) {
        v->not_found = 1;
        return NGX_OK;
    }

    auto *p = static_cast<u_char *>(ngx_pnalloc(r->pool, NGX_OFF_T_LEN));
    if (p == nullptr) {
        return NGX_ERROR;
    }

    v->data = p;

    auto *states =
        static_cast<ngx_http_upstream_state_t *>(r->upstream_states->elts);

    // The final upstream state always contains our response length
    p = ngx_sprintf(p, "%O",
                    states[r->upstream_states->nelts - 1].response_length);

    v->len = p - v->data;

    return NGX_OK;
}

/**
 * The module context contains initialization and configuration callbacks.
 */
ngx_http_module_t ngx_weserv_module_ctx = {
    // ngx_int_t (*preconfiguration)(ngx_conf_t *cf);
    ngx_weserv_preconfiguration,
    // ngx_int_t (*postconfiguration)(ngx_conf_t *cf);
    ngx_weserv_postconfiguration,
    // void *(*create_main_conf)(ngx_conf_t *cf);
    ngx_weserv_create_main_conf,
    // char *(*init_main_conf)(ngx_conf_t *cf, void *conf);
    nullptr,
    // void *(*create_srv_conf)(ngx_conf_t *cf);
    nullptr,
    // char *(*merge_srv_conf)(ngx_conf_t *cf, void *prev, void *conf);
    nullptr,
    // void *(*create_loc_conf)(ngx_conf_t *cf);
    ngx_weserv_create_loc_conf,
    // char *(*merge_loc_conf)(ngx_conf_t *cf, void *prev, void *conf);
    ngx_weserv_merge_loc_conf,
};

/**
 * The module's location callback directives.
 */
char *ngx_weserv(ngx_conf_t *cf, ngx_command_t *cmd, void *conf) {
    auto *clcf = static_cast<ngx_http_core_loc_conf_t *>(
        ngx_http_conf_get_module_loc_conf(cf, ngx_http_core_module));
    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(conf);

    char *rv = ngx_conf_set_enum_slot(cf, cmd, conf);
    if (rv != NGX_CONF_OK) {
        return rv;
    }

    // Only register the request handler for proxy mode
    if (lc->mode == NGX_WESERV_PROXY_MODE) {
        clcf->handler = ngx_weserv_request_handler;
    }

    lc->enable = 1;

    return NGX_CONF_OK;
}

char *ngx_weserv_deny_ip(ngx_conf_t *cf, ngx_command_t *cmd, void *conf) {
    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(conf);

    auto *value = static_cast<ngx_str_t *>(cf->args->elts);

    if (lc->deny == nullptr) {
        lc->deny = ngx_array_create(cf->pool, 2, sizeof(ngx_cidr_t));
        if (lc->deny == nullptr) {
            return static_cast<char *>(NGX_CONF_ERROR);
        }
    }

    ngx_cidr_t c;
    ngx_int_t rc = ngx_ptocidr(&value[1], &c);
    if (rc == NGX_ERROR) {
        return static_cast<char *>(NGX_CONF_ERROR);
    }

    if (rc == NGX_DONE) {
        ngx_conf_log_error(NGX_LOG_WARN, cf, 0,
                           "low address bits of %V are meaningless", &value[1]);
    }

    auto *cidr = static_cast<ngx_cidr_t *>(ngx_array_push(lc->deny));
    if (cidr == nullptr) {
        return static_cast<char *>(NGX_CONF_ERROR);
    }

    *cidr = c;

    return NGX_CONF_OK;
}

/**
 * Create weserv module's main context configuration
 */
void *ngx_weserv_create_main_conf(ngx_conf_t *cf) {
    auto *conf =
        register_pool_cleanup(cf->pool, new (cf->pool) ngx_weserv_main_conf_t);
    if (conf == nullptr) {
        return nullptr;
    }

    return conf;
}

/**
 * Create weserv module's location config.
 */
void *ngx_weserv_create_loc_conf(ngx_conf_t *cf) {
    auto *lc = new (cf->pool) ngx_weserv_loc_conf_t;
    if (lc == nullptr) {
        return nullptr;
    }

    lc->upstream_conf.connect_timeout = NGX_CONF_UNSET_MSEC;
    lc->upstream_conf.send_timeout = NGX_CONF_UNSET_MSEC;
    lc->upstream_conf.read_timeout = NGX_CONF_UNSET_MSEC;

    // The hardcoded values
    lc->upstream_conf.buffering = 1;
    lc->upstream_conf.buffer_size =
        4 * ngx_pagesize;  // 4 memory pages should be enough for most response
                           // headers
    lc->upstream_conf.busy_buffers_size =
        6 * ngx_pagesize;  // Essentially, buffer_size + 2 memory pages
    lc->upstream_conf.bufs.num = 256;
    lc->upstream_conf.bufs.size = ngx_pagesize;
    lc->upstream_conf.max_temp_file_size = 0;
    lc->upstream_conf.temp_file_write_size = 0;

    // Attempt to do a failover with a maximum of 3 tries when a domain name
    // resolves to several addresses
    lc->upstream_conf.next_upstream_tries = 3;
    lc->upstream_conf.next_upstream_timeout = 0;
    lc->upstream_conf.next_upstream =
        (NGX_CONF_BITMASK_SET | NGX_HTTP_UPSTREAM_FT_ERROR |
         NGX_HTTP_UPSTREAM_FT_TIMEOUT);

    // Do not pass the client request headers or body to the upstream
    lc->upstream_conf.pass_request_headers = 0;
    lc->upstream_conf.pass_request_body = 0;

    lc->upstream_conf.hide_headers =
        static_cast<ngx_array_t *>(NGX_CONF_UNSET_PTR);
    lc->upstream_conf.pass_headers =
        static_cast<ngx_array_t *>(NGX_CONF_UNSET_PTR);

    // Set up SSL if available
#if NGX_HTTP_SSL
    // Initialize SSL
    ngx_pool_cleanup_t *ssl_cleanup =
        ngx_pool_cleanup_add(cf->pool, sizeof(ngx_ssl_t));
    if (ssl_cleanup == nullptr) {
        return nullptr;
    }

    auto *ssl = static_cast<ngx_ssl_t *>(ssl_cleanup->data);
    ngx_memzero(ssl, sizeof(ngx_ssl_t));
    ssl->log = cf->log;

    if (ngx_ssl_create(ssl,
                       NGX_SSL_SSLv2 | NGX_SSL_SSLv3 | NGX_SSL_TLSv1 |
                           NGX_SSL_TLSv1_1 | NGX_SSL_TLSv1_2 | NGX_SSL_TLSv1_3,
                       nullptr) != NGX_OK) {
        return nullptr;
    }

#if OPENSSL_VERSION_NUMBER >= 0x10100000L && !defined(LIBRESSL_VERSION_NUMBER)
    // Use the lowest OpenSSL security level to ensure maximum compatibility.
    // This is basically the same as using OpenSSL 1.0, which lacks a minimum
    // security policy.
    SSL_CTX_set_security_level(ssl->ctx, 0);
#endif

    ssl_cleanup->handler = ngx_ssl_cleanup_ctx;

    lc->upstream_conf.ssl = ssl;
    lc->upstream_conf.ssl_session_reuse = 1;

    if (ngx_ssl_client_session_cache(cf, lc->upstream_conf.ssl,
                                     lc->upstream_conf.ssl_session_reuse) !=
        NGX_OK) {
        return nullptr;
    }

    // For SNI (Server Name Indication) support
    lc->upstream_conf.ssl_server_name = 1;
#endif

    lc->enable = NGX_CONF_UNSET;
    lc->mode = NGX_CONF_UNSET_UINT;
    lc->max_size = NGX_CONF_UNSET_SIZE;
    lc->max_redirects = NGX_CONF_UNSET_UINT;
    lc->canonical_header = NGX_CONF_UNSET;

    // API configuration
    lc->api_conf.savers = 0;
    lc->api_conf.process_timeout = NGX_CONF_UNSET;
    lc->api_conf.limit_input_pixels = NGX_CONF_UNSET_UINT;
    lc->api_conf.limit_output_pixels = NGX_CONF_UNSET_UINT;
    lc->api_conf.max_pages = NGX_CONF_UNSET;
    lc->api_conf.quality = NGX_CONF_UNSET;
    lc->api_conf.avif_quality = NGX_CONF_UNSET;
    lc->api_conf.jpeg_quality = NGX_CONF_UNSET;
    lc->api_conf.tiff_quality = NGX_CONF_UNSET;
    lc->api_conf.webp_quality = NGX_CONF_UNSET;
    lc->api_conf.avif_effort = NGX_CONF_UNSET;
    lc->api_conf.gif_effort = NGX_CONF_UNSET;
    lc->api_conf.webp_effort = NGX_CONF_UNSET;
    lc->api_conf.zlib_level = NGX_CONF_UNSET;
    lc->api_conf.fail_on_error = NGX_CONF_UNSET;

    return lc;
}

/**
 * Merge weserv module's location config.
 */
char *ngx_weserv_merge_loc_conf(ngx_conf_t *cf, void *parent, void *child) {
    auto *prev = static_cast<ngx_weserv_loc_conf_t *>(parent);
    auto *conf = static_cast<ngx_weserv_loc_conf_t *>(child);

    ngx_conf_merge_msec_value(conf->upstream_conf.connect_timeout,
                              prev->upstream_conf.connect_timeout, 5000);
    ngx_conf_merge_msec_value(conf->upstream_conf.send_timeout,
                              prev->upstream_conf.send_timeout, 5000);
    ngx_conf_merge_msec_value(conf->upstream_conf.read_timeout,
                              prev->upstream_conf.read_timeout, 15000);

    ngx_conf_merge_value(conf->enable, prev->enable, 0);
    ngx_conf_merge_uint_value(conf->mode, prev->mode, NGX_WESERV_PROXY_MODE);

    if (conf->deny == nullptr) {
        conf->deny = prev->deny;
    }

    ngx_conf_merge_str_value(conf->user_agent, prev->user_agent,
                             "Mozilla/5.0 (compatible; ImageFetcher/9.0; "
                             "+http://wsrv.nl/)");

    // Max image size to process is 100 MiB by default
    ngx_conf_merge_size_value(conf->max_size, prev->max_size,
                              100 * 1024 * 1024);

    // Follow 10 redirects by default
    ngx_conf_merge_uint_value(conf->max_redirects, prev->max_redirects, 10);

    // Set the rel="canonical" response header by default on proxied images
    ngx_conf_merge_value(conf->canonical_header, prev->canonical_header, 1);

    // All supported savers are enabled by default
    ngx_conf_merge_bitmask_value(
        conf->api_conf.savers, prev->api_conf.savers,
        (NGX_CONF_BITMASK_SET | static_cast<ngx_uint_t>(Output::All)));

    // Abort image processing after 10 seconds by default
    ngx_conf_merge_value(conf->api_conf.process_timeout,
                         prev->api_conf.process_timeout, 10);

    // Process at the maximum 256 pages, which should be plenty
    ngx_conf_merge_value(conf->api_conf.max_pages, prev->api_conf.max_pages,
                         256);

    // Do not process images where the number of pixels exceeds 71000000
    ngx_conf_merge_uint_value(conf->api_conf.limit_input_pixels,
                              prev->api_conf.limit_input_pixels, 71000000);

    // Do not output images where the number of pixels exceeds 71000000
    ngx_conf_merge_uint_value(conf->api_conf.limit_output_pixels,
                              prev->api_conf.limit_output_pixels, 71000000);

    // The default quality of 80 usually produces excellent results
    ngx_conf_merge_value(conf->api_conf.quality, prev->api_conf.quality, 80);
    ngx_conf_merge_value(conf->api_conf.avif_quality,
                         prev->api_conf.avif_quality, conf->api_conf.quality);
    ngx_conf_merge_value(conf->api_conf.jpeg_quality,
                         prev->api_conf.jpeg_quality, conf->api_conf.quality);
    ngx_conf_merge_value(conf->api_conf.tiff_quality,
                         prev->api_conf.tiff_quality, conf->api_conf.quality);
    ngx_conf_merge_value(conf->api_conf.webp_quality,
                         prev->api_conf.webp_quality, conf->api_conf.quality);

    // A default compromise between speed and compression effectiveness
    // (corresponds to the default values in libvips)
    ngx_conf_merge_value(conf->api_conf.avif_effort, prev->api_conf.avif_effort,
                         4);
    ngx_conf_merge_value(conf->api_conf.gif_effort, prev->api_conf.gif_effort,
                         7);
    ngx_conf_merge_value(conf->api_conf.webp_effort, prev->api_conf.webp_effort,
                         4);
    ngx_conf_merge_value(conf->api_conf.zlib_level, prev->api_conf.zlib_level,
                         6);

    // Do a "best effort" to decode images, even if the data is corrupt or
    // invalid
    ngx_conf_merge_value(conf->api_conf.fail_on_error,
                         prev->api_conf.fail_on_error, 0);

    return NGX_CONF_OK;
}

/**
 * weserv module initialization.
 */
ngx_int_t ngx_weserv_init_module(ngx_cycle_t *cycle) {
    ngx_log_debug0(NGX_LOG_DEBUG_HTTP, cycle->log, 0, "ngx_weserv_init_module");

    auto *mc = static_cast<ngx_weserv_main_conf_t *>(
        ngx_http_cycle_get_module_main_conf(cycle, ngx_weserv_module));
    if (mc == nullptr) {
        // Handle the case where there is no http section at all
        return NGX_OK;
    }

    api::ApiManagerFactory weserv_factory;
    mc->weserv = weserv_factory.create_api_manager(
        std::make_unique<NgxEnvironment>(cycle->log));

    return NGX_OK;
}

ngx_int_t ngx_weserv_image_header_filter(ngx_http_request_t *r) {
    if (r->headers_out.status == NGX_HTTP_NOT_MODIFIED) {
        return ngx_http_next_header_filter(r);
    }

    if (r != r->main) {
        return ngx_http_next_header_filter(r);
    }

    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (!lc->enable) {
        return ngx_http_next_header_filter(r);
    }

    auto *ctx = static_cast<ngx_weserv_base_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        // Context must always be available for proxy mode
        if (lc->mode == NGX_WESERV_PROXY_MODE) {
            return ngx_http_next_header_filter(r);
        }

        // For filter mode; we allocate and register a weserv base module
        // context on first use
        ctx = register_pool_cleanup(r->pool,
                                    new (r->pool) ngx_weserv_base_ctx_t());

        if (ctx == nullptr) {
            return NGX_ERROR;
        }

        // Set the request's weserv module context
        ngx_http_set_ctx(r, ctx, ngx_weserv_module);
    }

    off_t len = r->headers_out.content_length_n;

    if (len == -1) {
        ctx->length = lc->max_size;
    } else {
        ctx->length = static_cast<size_t>(len);
    }

    r->main_filter_need_in_memory = 1;

    ngx_http_clear_content_length(r);
    ngx_http_clear_accept_ranges(r);
    ngx_http_clear_last_modified(r);
    ngx_http_clear_etag(r);

    return NGX_OK;
}

ngx_int_t ngx_weserv_image_filter_read(ngx_http_request_t *r,
                                       ngx_weserv_base_ctx_t *ctx,
                                       ngx_chain_t *in) {
    if (ctx->image == nullptr) {
        ctx->image = static_cast<u_char *>(ngx_palloc(r->pool, ctx->length));
        if (ctx->image == nullptr) {
            return NGX_ERROR;
        }

        ctx->last = ctx->image;
    }

    u_char *p = ctx->last;

    for (ngx_chain_t *cl = in; cl; cl = cl->next) {
        ngx_buf_t *b = cl->buf;
        size_t size = b->last - b->pos;

        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                       "image buf: %uz", size);

        size_t rest = ctx->image + ctx->length - p;

        if (size > rest) {
            ngx_log_error(NGX_LOG_ERR, r->connection->log, 0,
                          "weserv image filter: too big response");
            return NGX_ERROR;
        }

        p = ngx_cpymem(p, b->pos, size);
        b->pos += size;

        if (b->last_buf) {
            ngx_free_chain(r->pool, cl);

            ctx->last = p;
            return NGX_OK;
        }
    }

    ctx->last = p;
    r->connection->buffered |= NGX_WESERV_IMAGE_BUFFERED;

    return NGX_AGAIN;
}

ngx_int_t ngx_weserv_finish(ngx_http_request_t *r, ngx_chain_t *out) {
    ngx_int_t rc = ngx_http_next_header_filter(r);

    if (rc == NGX_ERROR || rc > NGX_OK || r->header_only) {
        return NGX_ERROR;
    }

    return ngx_http_next_body_filter(r, out);
}

#if NGX_DEBUG
ngx_int_t ngx_weserv_finish_debug_chain(ngx_http_request_t *r,
                                        ngx_chain_t *out) {
    off_t content_length = 0;

    for (ngx_chain_t *cl = out; cl; cl = cl->next) {
        content_length += cl->buf->last - cl->buf->pos;
    }

    r->headers_out.status = NGX_HTTP_OK;
    r->headers_out.content_type_len = sizeof("text/plain") - 1;
    ngx_str_set(&r->headers_out.content_type, "text/plain");
    r->headers_out.content_type_lowcase = nullptr;
    r->headers_out.content_length_n = content_length;

    return ngx_weserv_finish(r, out);
}

ngx_int_t ngx_weserv_finish_debug_output(ngx_http_request_t *r,
                                         ngx_weserv_base_ctx_t *ctx) {
    size_t size = ctx->last - ctx->image;

    ngx_buf_t *buf = ngx_create_temp_buf(r->pool, size);
    if (buf == nullptr) {
        return NGX_ERROR;
    }

    buf->last_buf = 1;
    buf->last_in_chain = 1;
    buf->last = ngx_cpymem(buf->last, ctx->image, size);

    ngx_chain_t *out = ngx_alloc_chain_link(r->pool);
    if (out == nullptr) {
        return NGX_ERROR;
    }

    out->buf = buf;
    out->next = nullptr;

    return ngx_weserv_finish_debug_chain(r, out);
}
#endif

ngx_int_t ngx_weserv_image_body_filter(ngx_http_request_t *r, ngx_chain_t *in) {
    if (in == nullptr) {
        return ngx_http_next_body_filter(r, in);
    }

    if (r != r->main) {
        return ngx_http_next_body_filter(r, in);
    }

    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (!lc->enable) {
        return ngx_http_next_body_filter(r, in);
    }

    auto *ctx = static_cast<ngx_weserv_base_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    // Context must always be available
    if (ctx == nullptr) {
        return ngx_weserv_finish(r, in);
    }

#if NGX_DEBUG
    bool debug_output = false;
#endif

    ngx_weserv_upstream_ctx_t *upstream_ctx = nullptr;

    if (lc->mode == NGX_WESERV_PROXY_MODE) {
        upstream_ctx = dynamic_cast<ngx_weserv_upstream_ctx_t *>(ctx);

#if NGX_DEBUG
        if (upstream_ctx->debug == 1) {
            return ngx_weserv_finish_debug_chain(r, r->upstream->request_bufs);
        }

        debug_output = upstream_ctx->debug != 0;
#endif

        if (upstream_ctx->redirecting) {
            return NGX_AGAIN;
        }

        if (!upstream_ctx->response_status.ok()
#if NGX_DEBUG
            && !debug_output
#endif
        ) {
            ngx_chain_t *out = ngx_weserv_error_chain(
                r, upstream_ctx, upstream_ctx->response_status);
            if (out == NGX_CHAIN_ERROR) {
                return NGX_ERROR;
            }

            return ngx_weserv_finish(r, out);
        }
    }

    ngx_int_t rc = ngx_weserv_image_filter_read(r, ctx, in);
    if (rc == NGX_AGAIN) {
        return NGX_OK;
    }

    if (rc == NGX_ERROR) {
        Status status = {Status::Code::ImageTooLarge,
                         "The image is too large to be processed. "
                         "Max image size: " +
                             std::to_string(lc->max_size) + " bytes",
                         Status::ErrorCause::Application};

        ngx_chain_t *out = ngx_weserv_error_chain(r, upstream_ctx, status);
        if (out == NGX_CHAIN_ERROR) {
            return NGX_ERROR;
        }

        return ngx_weserv_finish(r, out);
    }

    r->connection->buffered &= ~NGX_WESERV_IMAGE_BUFFERED;

#if NGX_DEBUG
    if (debug_output) {
        return ngx_weserv_finish_debug_output(r, ctx);
    }
#endif

    auto *mc = static_cast<ngx_weserv_main_conf_t *>(
        ngx_http_get_module_main_conf(r, ngx_weserv_module));

    ngx_chain_t *out = nullptr;
    Status status = mc->weserv->process(
        ngx_str_to_std(r->args),
        std::make_unique<NgxSource>(ctx->image, ctx->last - ctx->image),
        std::make_unique<NgxTarget>(r, upstream_ctx, &out), lc->api_conf);

    // Memory is released immediately after the image output is complete,
    // without waiting for the entire response to be sent to the client
    ngx_pfree(r->pool, ctx->image);

    if (!status.ok()) {
        out = ngx_weserv_error_chain(r, upstream_ctx, status);
        if (out == NGX_CHAIN_ERROR) {
            return NGX_ERROR;
        }

        return ngx_weserv_finish(r, out);
    }

    if (is_base64_needed(r)) {
        out = output_chain_to_base64(r, out);
        if (out == NGX_CHAIN_ERROR) {
            return NGX_ERROR;
        }
    }

    return ngx_weserv_finish(r, out);
}

/*
 * The module pre-configuration. Define module variables.
 */
ngx_int_t ngx_weserv_preconfiguration(ngx_conf_t *cf) {
    // Define weserv variables
    for (ngx_http_variable_t *decl = ngx_weserv_vars; decl->name.len > 0;
         decl++) {
        ngx_http_variable_t *defn =
            ngx_http_add_variable(cf, &decl->name, decl->flags);
        if (defn == nullptr) {
            return NGX_ERROR;
        }

        defn->get_handler = decl->get_handler;
        defn->data = decl->data;
    }

    return NGX_OK;
}

/*
 * The module post-configuration. Insert filter handlers into NGINX's processing
 * pipeline.
 */
ngx_int_t ngx_weserv_postconfiguration(ngx_conf_t *cf) {
    ngx_http_next_header_filter = ngx_http_top_header_filter;
    ngx_http_top_header_filter = ngx_weserv_image_header_filter;

    ngx_http_next_body_filter = ngx_http_top_body_filter;
    ngx_http_top_body_filter = ngx_weserv_image_body_filter;

    return NGX_OK;
}

}  // namespace

}  // namespace weserv::nginx

/**
 * The module definition, referenced from the 'config' file.
 * N.B. This is the definition that's referenced by the nginx sources;
 * it must be globally scoped.
 */
ngx_module_t ngx_weserv_module = {
    NGX_MODULE_V1,                          // v1 module type
    &weserv::nginx::ngx_weserv_module_ctx,  // ctx
    weserv::nginx::ngx_weserv_commands,     // commands
    NGX_HTTP_MODULE,                        // type
    // ngx_int_t (*init_master)(ngx_log_t *log)
    nullptr,
    // ngx_int_t (*init_module)(ngx_cycle_t *cycle);
    weserv::nginx::ngx_weserv_init_module,
    // ngx_int_t (*init_process)(ngx_cycle_t *cycle);
    nullptr,
    // ngx_int_t (*init_thread)(ngx_cycle_t *cycle);
    nullptr,
    // void (*exit_thread)(ngx_cycle_t *cycle);
    nullptr,
    // void (*exit_process)(ngx_cycle_t *cycle);
    nullptr,
    // void (*exit_master)(ngx_cycle_t *cycle);
    nullptr,

    NGX_MODULE_V1_PADDING  // padding the rest of the ngx_module_t structure
};
