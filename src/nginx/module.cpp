#include "module.h"

#include "alloc.h"
#include "api.h"
#include "environment.h"
#include "handler.h"

using ::weserv::api::utils::Status;

namespace weserv {
namespace nginx {

namespace {
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
 * Post-configuration initialization.
 */
ngx_int_t ngx_weserv_postconfiguration(ngx_conf_t *cf);

ngx_http_output_header_filter_pt ngx_http_next_header_filter;
ngx_http_output_body_filter_pt ngx_http_next_body_filter;

ngx_conf_enum_t ngx_weserv_mode[] = {
    {ngx_string("proxy"), NGX_WESERV_PROXY_MODE},
    {ngx_string("file"), NGX_WESERV_FILE_MODE},
    {ngx_null_string, 0}  // last entry
};

/**
 * The module commands contain list of configurable properties for this module.
 */
ngx_command_t ngx_weserv_commands[] = {
    {
        ngx_string("weserv"),
        NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
            NGX_CONF_FLAG,
        ngx_conf_set_flag_slot,
        NGX_HTTP_LOC_CONF_OFFSET,
        offsetof(ngx_weserv_loc_conf_t, enable),
        nullptr,
    },
    {ngx_string("weserv_mode"), NGX_HTTP_LOC_CONF | NGX_CONF_TAKE1,
     ngx_conf_set_enum_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, mode), &ngx_weserv_mode},
    {ngx_string("weserv_connect_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.connect_timeout), nullptr},
    {ngx_string("weserv_send_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.send_timeout), nullptr},
    {ngx_string("weserv_read_timeout"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_msec_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, upstream_conf.read_timeout), nullptr},
    {ngx_string("weserv_user_agent"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_str_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, user_agent), nullptr},
    {
        ngx_string("weserv_max_size"),
        NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
            NGX_CONF_TAKE1,
        ngx_conf_set_size_slot,
        NGX_HTTP_LOC_CONF_OFFSET,
        offsetof(ngx_weserv_loc_conf_t, max_size),
        nullptr,
    },
    {ngx_string("weserv_max_redirects"),
     NGX_HTTP_MAIN_CONF | NGX_HTTP_SRV_CONF | NGX_HTTP_LOC_CONF |
         NGX_CONF_TAKE1,
     ngx_conf_set_num_slot, NGX_HTTP_LOC_CONF_OFFSET,
     offsetof(ngx_weserv_loc_conf_t, max_redirects), nullptr},
    ngx_null_command  // last entry
};

/**
 * The module context contains initialization and configuration callbacks.
 */
ngx_http_module_t ngx_weserv_module_ctx = {
    // ngx_int_t (*preconfiguration)(ngx_conf_t *cf);
    nullptr,
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
    auto *lc = reinterpret_cast<ngx_weserv_loc_conf_t *>(
        ngx_pcalloc(cf->pool, sizeof(ngx_weserv_loc_conf_t)));
    if (lc == nullptr) {
        return nullptr;
    }

    lc->upstream_conf.connect_timeout = NGX_CONF_UNSET_MSEC;
    lc->upstream_conf.send_timeout = NGX_CONF_UNSET_MSEC;
    lc->upstream_conf.read_timeout = NGX_CONF_UNSET_MSEC;

    // The hardcoded values
    lc->upstream_conf.buffer_size = ngx_pagesize;
    lc->upstream_conf.busy_buffers_size = 2 * ngx_pagesize;
    lc->upstream_conf.bufs.num = 256;
    lc->upstream_conf.bufs.size = ngx_pagesize;
    lc->upstream_conf.max_temp_file_size = 0;
    lc->upstream_conf.temp_file_write_size = 0;

    // Do not pass the client request headers or body to the upstream
    lc->upstream_conf.pass_request_headers = 0;
    lc->upstream_conf.pass_request_body = 0;

    lc->upstream_conf.hide_headers =
        reinterpret_cast<ngx_array_t *>(NGX_CONF_UNSET_PTR);
    lc->upstream_conf.pass_headers =
        reinterpret_cast<ngx_array_t *>(NGX_CONF_UNSET_PTR);

    // Set up SSL if available
#if NGX_HTTP_SSL
    // Initialize SSL
    auto ssl_cleanup = ngx_pool_cleanup_add(cf->pool, sizeof(ngx_ssl_t));
    if (ssl_cleanup == nullptr) {
        return nullptr;
    }
    auto *ssl = reinterpret_cast<ngx_ssl_t *>(ssl_cleanup->data);
    ngx_memzero(ssl, sizeof(ngx_ssl_t));
    ssl->log = cf->log;

    if (ngx_ssl_create(ssl,
                       NGX_SSL_SSLv2 | NGX_SSL_SSLv3 | NGX_SSL_TLSv1 |
                           NGX_SSL_TLSv1_1 | NGX_SSL_TLSv1_2 | NGX_SSL_TLSv1_3,
                       nullptr) != NGX_OK) {
        return nullptr;
    }

#if OPENSSL_VERSION_NUMBER >= 0x10100000L && !defined(LIBRESSL_VERSION_NUMBER)
    // Must use lowest OpenSSL security level to ensure maximum compatibility.
    // This is basically the same as using OpenSSL 1.0, which does not have a
    // minimum security policy.
    SSL_CTX_set_security_level(ssl->ctx, 0);
#endif

    ssl_cleanup->handler = ngx_ssl_cleanup_ctx;

    lc->upstream_conf.ssl = ssl;
    lc->upstream_conf.ssl_session_reuse = 1;

    // For SNI (Server Name Indication) support
    lc->upstream_conf.ssl_server_name = 1;
#endif

    lc->enable = NGX_CONF_UNSET;
    lc->mode = NGX_CONF_UNSET_UINT;
    lc->max_size = NGX_CONF_UNSET_SIZE;
    lc->max_redirects = NGX_CONF_UNSET_UINT;

    return lc;
}

/**
 * Merge weserv module's location config.
 */
char *ngx_weserv_merge_loc_conf(ngx_conf_t *cf, void *parent, void *child) {
    auto *prev = reinterpret_cast<ngx_weserv_loc_conf_t *>(parent);
    auto *conf = reinterpret_cast<ngx_weserv_loc_conf_t *>(child);

    ngx_conf_merge_msec_value(conf->upstream_conf.connect_timeout,
                              prev->upstream_conf.connect_timeout, 5000);
    ngx_conf_merge_msec_value(conf->upstream_conf.send_timeout,
                              prev->upstream_conf.send_timeout, 5000);
    ngx_conf_merge_msec_value(conf->upstream_conf.read_timeout,
                              prev->upstream_conf.read_timeout, 15000);

    ngx_conf_merge_value(conf->enable, prev->enable, 0);
    ngx_conf_merge_uint_value(conf->mode, prev->mode, NGX_WESERV_PROXY_MODE);

    ngx_conf_merge_str_value(conf->user_agent, prev->user_agent,
                             "Mozilla/5.0 (compatible; ImageFetcher/9.0; "
                             "+http://images.weserv.nl/)");

    // Max image size to process is 100 MiB by default
    ngx_conf_merge_size_value(conf->max_size, prev->max_size,
                              100 * 1024 * 1024);

    // We follow 10 redirects by default
    ngx_conf_merge_uint_value(conf->max_redirects, prev->max_redirects, 10);

    return reinterpret_cast<char *>(NGX_CONF_OK);
}

/**
 * weserv module initialization.
 */
ngx_int_t ngx_weserv_init_module(ngx_cycle_t *cycle) {
    ngx_log_debug0(NGX_LOG_DEBUG_HTTP, cycle->log, 0, "ngx_weserv_init_module");

    auto *mc = reinterpret_cast<ngx_weserv_main_conf_t *>(
        ngx_http_cycle_get_module_main_conf(cycle, ngx_weserv_module));
    if (mc == nullptr) {
        // Handle the case where there is no http section at all
        return NGX_OK;
    }

    api::ApiManagerFactory weserv_factory;
    mc->weserv = weserv_factory.create_api_manager(
        std::unique_ptr<api::ApiEnvInterface>(new NgxEnvironment(cycle->log)));

    return NGX_OK;
}

ngx_int_t ngx_weserv_image_header_filter(ngx_http_request_t *r) {
    if (r->headers_out.status == NGX_HTTP_NOT_MODIFIED) {
        return ngx_http_next_header_filter(r);
    }

    if (r != r->main) {
        return ngx_http_next_header_filter(r);
    }

    auto *lc = reinterpret_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (!lc->enable) {
        return ngx_http_next_header_filter(r);
    }

    auto *ctx = reinterpret_cast<ngx_weserv_filter_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        return ngx_http_next_header_filter(r);
    }

    off_t len = r->headers_out.content_length_n;

    if (len == -1) {
        ctx->length = lc->max_size;
    } else {
        ctx->length = static_cast<size_t>(len);
    }

    if (r->headers_out.refresh) {
        r->headers_out.refresh->hash = 0;
    }

    r->main_filter_need_in_memory = 1;
    r->allow_ranges = 0;

    return NGX_OK;
}

ngx_int_t ngx_weserv_image_read(ngx_http_request_t *r, ngx_chain_t *in,
                                ngx_weserv_filter_ctx_t *ctx) {
    if (ctx->image == nullptr) {
        ctx->image =
            reinterpret_cast<u_char *>(ngx_palloc(r->pool, ctx->length));
        if (ctx->image == nullptr) {
            return NGX_ERROR;
        }

        ctx->last = ctx->image;
    }

    u_char *p = ctx->last;
    size_t size, rest;
    ngx_buf_t *b;
    ngx_chain_t *cl;

    for (cl = in; cl; cl = cl->next) {
        b = cl->buf;
        size = b->last - b->pos;

        ngx_log_debug1(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                       "image buf: %uz", size);

        rest = ctx->image + ctx->length - p;

        if (size > rest) {
            ngx_log_error(NGX_LOG_ERR, r->connection->log, 0,
                          "weserv image filter: too big response");
            return NGX_ERROR;
        }

        p = ngx_cpymem(p, b->pos, size);
        b->pos += size;

        if (b->last_buf) {
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

ngx_int_t ngx_weserv_image_body_filter(ngx_http_request_t *r, ngx_chain_t *in) {
    if (in == nullptr) {
        return ngx_http_next_body_filter(r, in);
    }

    if (r != r->main) {
        return ngx_http_next_body_filter(r, in);
    }

    auto *lc = reinterpret_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (!lc->enable) {
        return ngx_http_next_body_filter(r, in);
    }

    auto *ctx = reinterpret_cast<ngx_weserv_base_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        return ngx_weserv_finish(r, in);
    }

    if (ctx->id() == NGX_WESERV_UPSTREAM_CTX) {
        auto *upstream = reinterpret_cast<ngx_weserv_upstream_ctx_t *>(ctx);

        ngx_weserv_http_connection *http_connection = upstream->http_subrequest;
        if (http_connection->redirecting) {
            return NGX_AGAIN;
        } else if (!http_connection->response_status.ok()) {
            ngx_chain_t out;
            if (ngx_weserv_return_error(r, http_connection->response_status,
                                        &out) != NGX_OK) {
                return NGX_ERROR;
            }

            return ngx_weserv_finish(r, &out);
        }
    }

    auto *filter_ctx = reinterpret_cast<ngx_weserv_filter_ctx_t *>(ctx);

    ngx_int_t rc = ngx_weserv_image_read(r, in, filter_ctx);
    if (rc == NGX_AGAIN) {
        return NGX_OK;
    }

    if (rc == NGX_ERROR) {
        Status status = Status(Status::Code::ImageTooLarge,
                               "The image is too large to be processed. "
                               "Max image size: " +
                                   std::to_string(lc->max_size) + " bytes",
                               Status::ErrorCause::Application);

        ngx_chain_t out;
        if (ngx_weserv_return_error(r, status, &out) != NGX_OK) {
            return NGX_ERROR;
        }

        return ngx_weserv_finish(r, &out);
    }

    r->connection->buffered &= ~NGX_WESERV_IMAGE_BUFFERED;

    ngx_chain_t out;
    if (ngx_weserv_process(r, filter_ctx, &out) != NGX_OK) {
        return NGX_ERROR;
    }

    return ngx_weserv_finish(r, &out);
}

ngx_int_t ngx_weserv_postconfiguration(ngx_conf_t *cf) {
    ngx_http_next_header_filter = ngx_http_top_header_filter;
    ngx_http_top_header_filter = ngx_weserv_image_header_filter;

    ngx_http_next_body_filter = ngx_http_top_body_filter;
    ngx_http_top_body_filter = ngx_weserv_image_body_filter;

    auto *cmcf = reinterpret_cast<ngx_http_core_main_conf_t *>(
        ngx_http_conf_get_module_main_conf(cf, ngx_http_core_module));

    auto *h = reinterpret_cast<ngx_http_handler_pt *>(
        ngx_array_push(&cmcf->phases[NGX_HTTP_CONTENT_PHASE].handlers));

    if (h == nullptr) {
        return NGX_ERROR;
    }

    *h = ngx_weserv_request_handler;

    return NGX_OK;
}

}  // namespace

}  // namespace nginx
}  // namespace weserv

/**
 * The module definition, referenced from the 'config' file.
 * N.B. This is the definition that's referenced by the nginx sources;
 * it must be globally scoped.
 */
ngx_module_t ngx_weserv_module = {
    NGX_MODULE_V1,                            // v1 module type
    &::weserv::nginx::ngx_weserv_module_ctx,  // ctx
    ::weserv::nginx::ngx_weserv_commands,     // commands
    NGX_HTTP_MODULE,                          // type
    // ngx_int_t (*init_master)(ngx_log_t *log)
    nullptr,
    // ngx_int_t (*init_module)(ngx_cycle_t *cycle);
    ::weserv::nginx::ngx_weserv_init_module,
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
