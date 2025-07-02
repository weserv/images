#include "http_filter.h"

#include "module.h"

using weserv::api::utils::Status;

namespace weserv::nginx {

ngx_int_t check_image_too_large(ngx_event_pipe_t *p) {
    auto *r = static_cast<ngx_http_request_t *>(p->input_ctx);
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *lc = static_cast<ngx_weserv_loc_conf_t *>(
        ngx_http_get_module_loc_conf(r, ngx_weserv_module));

    if (lc->max_size > 0 && p->read_length > static_cast<off_t>(lc->max_size)) {
        auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
            ngx_http_get_module_ctx(r, ngx_weserv_module));

        if (ctx == nullptr) {
            return NGX_ERROR;
        }

        ngx_log_error(NGX_LOG_ERR, p->log, 0,
                      "upstream has sent an too large body: %O bytes",
                      p->read_length);

        ctx->response_status = {413,
                                "The image is too large to be downloaded. "
                                "Max image size: " +
                                    std::to_string(lc->max_size) + " bytes",
                                Status::ErrorCause::Upstream};

        return NGX_ERROR;
    }

    return NGX_OK;
}

ngx_int_t ngx_weserv_copy_filter(ngx_event_pipe_t *p, ngx_buf_t *buf) {
    if (buf->pos == buf->last) {
        return NGX_OK;
    }

    if (p->upstream_done) {
        ngx_log_debug0(NGX_LOG_DEBUG_HTTP, p->log, 0,
                       "weserv data after close");
        return NGX_OK;
    }

    if (p->length == 0) {
        ngx_log_error(NGX_LOG_WARN, p->log, 0,
                      "upstream sent more data than specified in "
                      "\"Content-Length\" header");

        auto *r = static_cast<ngx_http_request_t *>(p->input_ctx);
        r->upstream->keepalive = 0;
        p->upstream_done = 1;

        return NGX_OK;
    }

    ngx_chain_t *cl = ngx_chain_get_free_buf(p->pool, &p->free);
    if (cl == nullptr) {
        return NGX_ERROR;
    }

    ngx_buf_t *b = cl->buf;

    ngx_memcpy(b, buf, sizeof(ngx_buf_t));
    b->shadow = buf;
    b->tag = p->tag;
    b->last_shadow = 1;
    b->recycled = 1;
    buf->shadow = b;

    ngx_log_debug1(NGX_LOG_DEBUG_EVENT, p->log, 0, "input buf #%d", b->num);

    if (p->in) {
        *p->last_in = cl;
    } else {
        p->in = cl;
    }
    p->last_in = &cl->next;

    // Is the content length header available?
    if (p->length == -1) {
        if (check_image_too_large(p) != NGX_OK) {
            p->upstream_done = 1;

            return NGX_HTTP_REQUEST_ENTITY_TOO_LARGE;
        }

        return NGX_OK;
    }

    if (b->last - b->pos > p->length) {
        ngx_log_error(NGX_LOG_WARN, p->log, 0,
                      "upstream sent more data than specified in "
                      "\"Content-Length\" header");
        b->last = b->pos + p->length;
        p->upstream_done = 1;

        return NGX_OK;
    }

    p->length -= b->last - b->pos;

    if (p->length == 0) {
        auto *r = static_cast<ngx_http_request_t *>(p->input_ctx);
        r->upstream->keepalive = !r->upstream->headers_in.connection_close;
    }

    return NGX_OK;
}

/**
 * Reference: ngx_http_proxy_chunked_filter
 */
ngx_int_t ngx_weserv_chunked_filter(ngx_event_pipe_t *p, ngx_buf_t *buf) {
    if (buf->pos == buf->last) {
        return NGX_OK;
    }

    auto *r = static_cast<ngx_http_request_t *>(p->input_ctx);
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

    if (p->upstream_done) {
        ngx_log_debug0(NGX_LOG_DEBUG_HTTP, p->log, 0,
                       "weserv data after close");
        return NGX_OK;
    }

    if (p->length == 0) {
        ngx_log_error(NGX_LOG_WARN, p->log, 0,
                      "upstream sent data after final chunk");

        r->upstream->keepalive = 0;
        p->upstream_done = 1;

        return NGX_OK;
    }

    ngx_buf_t *b = nullptr;
    ngx_buf_t **prev = &buf->shadow;

    for (;;) {
        // In NGINX version 1.27.2, the signature of ngx_http_parse_chunked()
        // was modified to enable passing upstream response trailers to the
        // client.
        ngx_int_t rc = ngx_http_parse_chunked(r, buf, &ctx->chunked
#if defined(nginx_version) && nginx_version >= 1027002
                                              , 0
#endif
        );

        if (rc == NGX_OK) {
            // A chunk has been parsed successfully

            ngx_chain_t *cl = ngx_chain_get_free_buf(p->pool, &p->free);
            if (cl == nullptr) {
                return NGX_ERROR;
            }

            b = cl->buf;

            ngx_memzero(b, sizeof(ngx_buf_t));

            b->pos = buf->pos;
            b->start = buf->start;
            b->end = buf->end;
            b->tag = p->tag;
            b->temporary = 1;
            b->recycled = 1;

            *prev = b;
            prev = &b->shadow;

            if (p->in) {
                *p->last_in = cl;
            } else {
                p->in = cl;
            }
            p->last_in = &cl->next;

            /* STUB */ b->num = buf->num;

            ngx_log_debug2(NGX_LOG_DEBUG_EVENT, p->log, 0, "input buf #%d %p",
                           b->num, b->pos);

            if (buf->last - buf->pos >= ctx->chunked.size) {
                buf->pos += static_cast<size_t>(ctx->chunked.size);
                b->last = buf->pos;
                ctx->chunked.size = 0;

                continue;
            }

            ctx->chunked.size -= buf->last - buf->pos;
            buf->pos = buf->last;
            b->last = buf->last;

            continue;
        }

        if (rc == NGX_DONE) {
            // A whole response has been parsed successfully

            p->length = 0;
            r->upstream->keepalive = !r->upstream->headers_in.connection_close;

            if (buf->pos != buf->last) {
                ngx_log_error(NGX_LOG_WARN, p->log, 0,
                              "upstream sent data after final chunk");
                r->upstream->keepalive = 0;
            }

            break;
        }

        if (rc == NGX_AGAIN) {
            // Set p->length, minimal amount of data we want to see

            p->length = ctx->chunked.length;

            break;
        }

        // Invalid response
        ngx_log_error(NGX_LOG_ERR, p->log, 0,
                      "upstream sent invalid chunked response");

        return NGX_ERROR;
    }

    ngx_log_debug2(NGX_LOG_DEBUG_HTTP, p->log, 0,
                   "weserv chunked state %ui, length %O", ctx->chunked.state,
                   p->length);

    if (check_image_too_large(p) != NGX_OK) {
        p->upstream_done = 1;

        return NGX_HTTP_REQUEST_ENTITY_TOO_LARGE;
    }

    if (b != nullptr) {
        b->shadow = buf;
        b->last_shadow = 1;

        ngx_log_debug2(NGX_LOG_DEBUG_EVENT, p->log, 0, "input buf %p %z",
                       b->pos, b->last - b->pos);

        return NGX_OK;
    }

    // There is no data record in the buf, add it to free chain
    if (ngx_event_pipe_add_free_buf(p, buf) != NGX_OK) {
        return NGX_ERROR;
    }

    return NGX_OK;
}

ngx_int_t ngx_weserv_input_filter_init(void *data) {
    auto *r = static_cast<ngx_http_request_t *>(data);
    if (r == nullptr) {
        return NGX_ERROR;
    }

    auto *ctx = static_cast<ngx_weserv_upstream_ctx_t *>(
        ngx_http_get_module_ctx(r, ngx_weserv_module));

    if (ctx == nullptr) {
        return NGX_ERROR;
    }

    ngx_http_upstream_t *u = r->upstream;

    ngx_log_debug3(NGX_LOG_DEBUG_HTTP, r->connection->log, 0,
                   "weserv upstream filter initialized s:%ui c:%d l:%O",
                   ctx->response_status.code(), u->headers_in.chunked,
                   u->headers_in.content_length_n);

    // As per RFC2616, 4.4 Message Length

    if (u->headers_in.chunked) {
        // Chunked

        u->pipe->input_filter = ngx_weserv_chunked_filter;
        u->pipe->length = 3;  // "0" LF LF
    } else if (u->headers_in.content_length_n == 0) {
        // Empty body: special case as filter won't be called

        u->pipe->length = 0;
        u->keepalive = !u->headers_in.connection_close;
    } else {
        // Content length or connection close

        u->pipe->length = u->headers_in.content_length_n;
    }

    return NGX_OK;
}

}  // namespace weserv::nginx
