#include "handler.h"

#include "alloc.h"
#include "error.h"
#include "http.h"
#include "uri_parser.h"
#include "util.h"

#include <memory>
#include <string>
#include <utility>

using ::weserv::api::utils::Status;

namespace weserv::nginx {

ngx_int_t ngx_weserv_request_handler(ngx_http_request_t *r) {
    auto *lc = reinterpret_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    // Response to 'GET' and 'HEAD' requests only
    if (!(r->method & (NGX_HTTP_GET | NGX_HTTP_HEAD))) {
        return NGX_HTTP_NOT_ALLOWED;
    }

    // Discard request body, since we don't need it here
    ngx_int_t rc = ngx_http_discard_request_body(r);
    if (rc != NGX_OK) {
        return rc;
    }

    ngx_str_t uri;
    if (ngx_http_arg(r, (u_char *)"url", 3, &uri) != NGX_OK) {
        return NGX_DECLINED;
    }

    ngx_str_t parsed_uri;
    if (parse_url(r->pool, uri, &parsed_uri) != NGX_OK) {
        Status status = Status(Status::Code::InvalidUri, "Unable to parse URI",
                               Status::ErrorCause::Application);

        ngx_chain_t out;
        if (ngx_weserv_return_error(r, status, &out) != NGX_OK) {
            return NGX_ERROR;
        }

        return ngx_http_output_filter(r, &out);
    }

    // Allocate a weserv upstream module context
    auto *ctx = register_pool_cleanup(r->pool, new (r->pool)
                                                   ngx_weserv_upstream_ctx_t());
    if (ctx == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

#if NGX_DEBUG
    ngx_str_t debug;
    if (ngx_http_arg(r, (u_char *)"debug", 5, &debug) == NGX_OK) {
        ctx->debug = ngx_atoof(debug.data, debug.len);

        if (ctx->debug == NGX_ERROR || ctx->debug > 3) {
            ctx->debug = 0;
        }
    } else {
        ctx->debug = 0;
    }
#endif

    // Set the request's weserv module context
    ngx_http_set_ctx(r, ctx, ngx_weserv_module);

    std::unique_ptr<HTTPRequest> http_request(new HTTPRequest);
    http_request->set_url(parsed_uri)
        .set_max_redirects(lc->max_redirects)
        .set_header("User-Agent", lc->user_agent);

    // Store the caller's request
    ctx->request = std::move(http_request);

    rc = ngx_weserv_send_http_request(r, ctx);

    if (rc == NGX_ERROR) {
        ngx_chain_t out;
        if (ngx_weserv_return_error(r, ctx->response_status, &out) != NGX_OK) {
            return NGX_ERROR;
        }

        // Don't forget to reset the module context set above
        ngx_http_set_ctx(r, nullptr, ngx_weserv_module);

        return ngx_http_output_filter(r, &out);
    }

    return rc;
}

}  // namespace weserv::nginx
