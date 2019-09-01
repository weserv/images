#include "api.h"

#include "header.h"
#include "uri_parser.h"
#include "util.h"

using ::weserv::api::utils::Status;

namespace weserv {
namespace nginx {

ngx_str_t application_json = ngx_string("application/json");
ngx_str_t text_plain = ngx_string("text/plain");

// 1 year by default.
// See: https://github.com/weserv/images/issues/186
const time_t MAX_AGE_DEFAULT = 60 * 60 * 24 * 365;

ngx_int_t ngx_weserv_return_error(ngx_http_request_t *r, Status status,
                                  ngx_chain_t *out) {
    ngx_uint_t http_status = status.http_code();

    // Redirect if the 'default' (or 'errorredirect') query parameter is given.
    // Note that the 'errorredirect' parameter was deprecated since API 5
    // and is only used here for backward compatible reasons.
    ngx_str_t redirect_uri;
    if (ngx_http_arg(r, (u_char *)"default", 7, &redirect_uri) == NGX_OK ||
        ngx_http_arg(r, (u_char *)"errorredirect", 13, &redirect_uri) ==
            NGX_OK) {
        // TODO(kleisauke): Should we remove all 'default' and 'errorredirect'
        // query parameters from the redirect URI to avoid redirection loops?
        // (perhaps it might be useful to return an error(?))

        ngx_str_t parsed_redirect;
        if (parse_url(r->pool, redirect_uri, &parsed_redirect) == NGX_OK) {
            if (set_location_header(r, &parsed_redirect) != NGX_OK) {
                return NGX_HTTP_INTERNAL_SERVER_ERROR;
            }

            http_status = NGX_HTTP_MOVED_TEMPORARILY;
        }
    }

    std::string error = status.to_json();

    // Create temporary buffer to hold data
    auto *b = reinterpret_cast<ngx_buf_t *>(ngx_calloc_buf(r->pool));
    if (b == nullptr) {
        return NGX_ERROR;
    }

    off_t content_length = error.size();
    ngx_buf_t *buf = ngx_create_temp_buf(r->pool, content_length);
    if (buf == nullptr) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    buf->temporary = 1;
    buf->last_buf = 1;
    buf->last_in_chain = 1;

    ngx_memcpy(buf->last, error.c_str(), content_length);
    buf->last += content_length;

    r->headers_out.status = http_status;
    r->headers_out.content_type = application_json;
    r->headers_out.content_type_len = application_json.len;
    r->headers_out.content_type_lowcase = nullptr;
    r->headers_out.content_length_n = content_length;

    if (r->headers_out.content_length) {
        r->headers_out.content_length->hash = 0;
    }

    r->headers_out.content_length = nullptr;

    *out = {buf, nullptr};

    return NGX_OK;
}

ngx_int_t ngx_weserv_process(ngx_http_request_t *r,
                             ngx_weserv_filter_ctx_t *ctx, ngx_chain_t *out) {
    auto *mc = reinterpret_cast<ngx_weserv_main_conf_t *>(
        ngx_http_get_module_main_conf(r, ngx_weserv_module));

    // Extract response body to a string
    std::string body(reinterpret_cast<char *>(ctx->image),
                     ctx->last - ctx->image);

    std::string content;
    std::string extension;

    Status status = mc->weserv->process(ngx_str_to_std(r->args), body, &content,
                                        &extension);

    if (!status.ok()) {
        return ngx_weserv_return_error(r, status, out);
    }

    ngx_str_t mime_type = extension_to_mime_type(extension);

    // Create temporary buffer to hold data
    auto *b = reinterpret_cast<ngx_buf_t *>(ngx_calloc_buf(r->pool));
    if (b == nullptr) {
        return NGX_ERROR;
    }

    ngx_buf_t *buf;
    ngx_str_t content_type;
    off_t content_length;
    if (is_base64_needed(r)) {
        content_type = text_plain;

        size_t prefix_size = sizeof("data:") - 1;
        size_t suffix_size = sizeof(";base64,") - 1;

        ngx_str_t dst, src;
        if (ngx_str_copy_from_std(r->pool, content, &src) != NGX_OK) {
            return NGX_HTTP_INTERNAL_SERVER_ERROR;
        }

        int enc_len = ngx_base64_encoded_length(src.len);
        dst.data =
            reinterpret_cast<u_char *>(ngx_pcalloc(r->pool, enc_len + 1));

        ngx_encode_base64(&dst, &src);
        dst.data[dst.len] = '\0';

        content_length = prefix_size + mime_type.len + suffix_size + dst.len;

        buf = ngx_create_temp_buf(r->pool, content_length);
        if (buf == nullptr) {
            return NGX_HTTP_INTERNAL_SERVER_ERROR;
        }

        buf->temporary = 1;
        buf->last_buf = 1;
        buf->last_in_chain = 1;
        buf->last = ngx_cpymem(buf->last, "data:", prefix_size);
        buf->last = ngx_cpymem(buf->last, mime_type.data, mime_type.len);
        buf->last = ngx_cpymem(buf->last, ";base64,", suffix_size);

        ngx_memcpy(buf->last, dst.data, dst.len);
        buf->last += dst.len;
    } else {
        content_type = mime_type;
        content_length = content.size();

        buf = ngx_create_temp_buf(r->pool, content_length);
        if (buf == nullptr) {
            return NGX_HTTP_INTERNAL_SERVER_ERROR;
        }

        buf->temporary = 1;
        buf->last_buf = 1;
        buf->last_in_chain = 1;

        ngx_memcpy(buf->last, content.data(), content_length);
        buf->last += content_length;
    }

    r->headers_out.status = NGX_HTTP_OK;
    r->headers_out.content_type = content_type;
    r->headers_out.content_type_len = content_type.len;
    r->headers_out.content_type_lowcase = nullptr;
    r->headers_out.content_length_n = content_length;

    if (r->headers_out.content_length) {
        r->headers_out.content_length->hash = 0;
    }

    r->headers_out.content_length = nullptr;

    // Set the content disposition header to images only
    if (!ngx_string_equal(content_type, application_json) &&
        !ngx_string_equal(content_type, text_plain)) {
        ngx_str_t content_disposition;
        if (get_content_disposition(r, extension, &content_disposition) !=
                NGX_OK ||
            set_content_disposition_header(r, &content_disposition) != NGX_OK) {
            return NGX_HTTP_INTERNAL_SERVER_ERROR;
        }
    }

    time_t max_age = MAX_AGE_DEFAULT;

    ngx_str_t max_age_str;
    if (ngx_http_arg(r, (u_char *)"maxage", 6, &max_age_str) == NGX_OK) {
        max_age = parse_max_age(max_age_str);
        if (max_age == static_cast<time_t>(NGX_ERROR)) {
            max_age = MAX_AGE_DEFAULT;
        }
    }

    // Only set Cache-Control and Expires headers on non-error responses
    if (set_expires_header(r, max_age) != NGX_OK) {
        return NGX_HTTP_INTERNAL_SERVER_ERROR;
    }

    *out = {buf, nullptr};

    return NGX_OK;
}

}  // namespace nginx
}  // namespace weserv
