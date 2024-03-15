#include "error.h"

#include "header.h"
#include "uri_parser.h"
#include "util.h"

#include <string>

using ::weserv::api::utils::Status;

namespace weserv::nginx {

ngx_int_t ngx_weserv_return_error(ngx_http_request_t *r,
                                  ngx_weserv_upstream_ctx_t *upstream_ctx,
                                  Status status, ngx_chain_t *out) {
    ngx_uint_t http_status = status.http_code();

    // Redirect if the 'default' (or 'errorredirect') query parameter is given.
    // Note that the 'errorredirect' parameter was deprecated since API 5
    // and is only used here for backward compatible reasons.
    ngx_str_t redirect_uri;
    if (ngx_http_arg(r, (u_char *)"default", 7, &redirect_uri) == NGX_OK ||
        ngx_http_arg(r, (u_char *)"errorredirect", 13, &redirect_uri) ==
            NGX_OK) {
        ngx_str_t parsed_redirect = ngx_null_string;
        if (redirect_uri.len != 1 || redirect_uri.data[0] != '1') {
            (void)parse_url(r->pool, redirect_uri, &parsed_redirect);
        } else if (upstream_ctx != nullptr &&
                   upstream_ctx->request != nullptr) {
            // NB: ->request will be NULL in case of redirect errors.
            parsed_redirect = upstream_ctx->request->url();
        }

        if (parsed_redirect.len > 0 &&
            set_location_header(r, &parsed_redirect) == NGX_OK) {
            http_status = NGX_HTTP_MOVED_TEMPORARILY;
        }
    }

    std::string error = status.to_json();

    off_t content_length = error.size();
    ngx_buf_t *buf = ngx_create_temp_buf(r->pool, content_length);
    if (buf == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    buf->last_buf = 1;
    buf->last_in_chain = 1;
    buf->last = ngx_cpymem(buf->last, error.c_str(), content_length);

    r->headers_out.status = http_status;
    r->headers_out.content_type_len = sizeof("application/json") - 1;
    ngx_str_set(&r->headers_out.content_type, "application/json");
    r->headers_out.content_type_lowcase = nullptr;
    r->headers_out.content_length_n = content_length;

    if (r->headers_out.content_length) {
        r->headers_out.content_length->hash = 0;
    }

    r->headers_out.content_length = nullptr;

    *out = {buf, nullptr};

    return NGX_OK;
}

}  // namespace weserv::nginx
