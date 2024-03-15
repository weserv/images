#include "util.h"

namespace weserv::nginx {

std::string ngx_str_to_std(const ngx_str_t &src) {
    return (src.data == nullptr || src.len <= 0)
               ? std::string()
               : std::string(reinterpret_cast<const char *>(src.data), src.len);
}

bool has_valid_scheme(const ngx_str_t &url) {
    return (url.len > 7 &&
            ngx_strncasecmp(url.data, (u_char *)"http://", 7) == 0) ||
           (url.len > 8 &&
            ngx_strncasecmp(url.data, (u_char *)"https://", 8) == 0);
}

bool is_base64_needed(ngx_http_request_t *r) {
    ngx_str_t encoding;
    if (ngx_http_arg(r, (u_char *)"encoding", 8, &encoding) != NGX_OK) {
        return false;
    }

    return encoding.len == 6 &&
           ngx_strncasecmp(encoding.data, (u_char *)"base64", 6) == 0;
}

ngx_int_t output_chain_to_base64(ngx_http_request_t *r, ngx_chain_t *out) {
    size_t prefix_size = sizeof("data:") - 1;
    size_t suffix_size = sizeof(";base64,") - 1;
    off_t content_length = r->headers_out.content_length_n;

    ngx_str_t src;
    src.data = reinterpret_cast<u_char *>(ngx_palloc(r->pool, content_length));
    if (src.data == nullptr) {
        return NGX_ERROR;
    }

    u_char *p = src.data;

    for (ngx_chain_t *cl = out; cl; cl = cl->next) {
        ngx_buf_t *b = cl->buf;
        size_t size = b->last - b->pos;

        size_t rest = src.data + content_length - p;

        if (size > rest) {
            ngx_log_error(NGX_LOG_ERR, r->connection->log, 0,
                          "weserv image filter: too big response");
            return NGX_ERROR;
        }

        p = ngx_cpymem(p, b->pos, size);
        b->pos += size;
    }

    src.len = p - src.data;

    ngx_str_t base64;
    base64.len = ngx_base64_encoded_length(src.len);
    base64.data = reinterpret_cast<u_char *>(ngx_palloc(r->pool, base64.len));
    if (base64.data == nullptr) {
        return NGX_ERROR;
    }

    ngx_encode_base64(&base64, &src);

    ngx_str_t mime_type = r->headers_out.content_type;
    content_length = prefix_size + mime_type.len + suffix_size + base64.len;

    ngx_buf_t *buf = ngx_create_temp_buf(r->pool, content_length);
    if (buf == nullptr) {
        return NGX_ERROR;
    }

    buf->last_buf = 1;
    buf->last_in_chain = 1;
    buf->last = ngx_cpymem(buf->last, "data:", prefix_size);
    buf->last = ngx_cpymem(buf->last, mime_type.data, mime_type.len);
    buf->last = ngx_cpymem(buf->last, ";base64,", suffix_size);
    buf->last = ngx_cpymem(buf->last, base64.data, base64.len);

    r->headers_out.content_length_n = content_length;
    r->headers_out.content_type_len = sizeof("text/plain") - 1;
    ngx_str_set(&r->headers_out.content_type, "text/plain");

    *out = {buf, nullptr};

    return NGX_OK;
}

time_t parse_max_age(ngx_str_t &s) {
    time_t max_age = ngx_parse_time(&s, 1);
    if (max_age == static_cast<time_t>(NGX_ERROR)) {
        return NGX_ERROR;
    }

    // We don't want shorter max-ages than 1 day, see:
    // https://github.com/weserv/images/issues/292
    if (max_age < 60 * 60 * 24) {
        return NGX_ERROR;
    }

    // One year is advised as a standard max value as per RFC2616, 14.21 Expires
    if (max_age > 60 * 60 * 24 * 365) {
        return NGX_ERROR;
    }

    return max_age;
}

ngx_str_t extension_to_mime_type(const std::string &extension) {
    if (extension == ".jpg") {
        return ngx_string("image/jpeg");
    }
    if (extension == ".png") {
        return ngx_string("image/png");
    }
    if (extension == ".webp") {
        return ngx_string("image/webp");
    }
    if (extension == ".avif") {
        return ngx_string("image/avif");
    }
    if (extension == ".tiff") {
        return ngx_string("image/tiff");
    }
    if (extension == ".gif") {
        return ngx_string("image/gif");
    }
    // if (extension == ".json")

    return ngx_string("application/json");
}

namespace {

const char *log_levels[] = {
    "",        // NGX_LOG_STDERR
    "emerg",   // NGX_LOG_EMERG
    "alert",   // NGX_LOG_ALERT
    "crit",    // NGX_LOG_CRIT
    "error",   // NGX_LOG_ERR
    "warn",    // NGX_LOG_WARN
    "notice",  // NGX_LOG_NOTICE
    "info",    // NGX_LOG_INFO
    "debug",   // NGX_LOG_DEBUG
};
const ngx_uint_t LOG_LEVELS_COUNT = sizeof(log_levels) / sizeof(log_levels[0]);

}  // namespace

#define HEADER_SIZE 1024
#define TRAILER_SIZE 512

void ngx_weserv_log(ngx_log_t *log, ngx_uint_t level, ngx_str_t msg) {
    if (!log || log->log_level < level)
        return;

    if (level >= LOG_LEVELS_COUNT) {
        level = LOG_LEVELS_COUNT - 1;
    }

    u_char header[HEADER_SIZE];
    u_char trailer[TRAILER_SIZE];
    u_char *h = header, *const last = header + HEADER_SIZE;
    u_char *t = trailer;

    h = ngx_cpymem(h, ngx_cached_err_log_time.data,
                   ngx_cached_err_log_time.len);
    h = ngx_slprintf(h, last, " [%s] ", log_levels[level]);
    h = ngx_slprintf(h, last, "%P#" NGX_TID_T_FMT ": ", ngx_log_pid,
                     ngx_log_tid);
    if (log->connection) {
        h = ngx_slprintf(h, last, "*%uA ", log->connection);
    }
    if (level != NGX_LOG_DEBUG && log->handler) {
        t = log->handler(log, t, TRAILER_SIZE - 1);  // space for ngx_linefeed
    }
    ngx_linefeed(t);

    bool debug_connection = log->log_level & NGX_LOG_DEBUG_CONNECTION;
    bool wrote_stderr = false;

    for (; log; log = log->next) {
        if (log->log_level < level && !debug_connection) {
            break;
        }

        if (log->writer) {
            log->writer(log, level, header, h - header);
            log->writer(log, level, msg.data, msg.len);
            log->writer(log, level, trailer, t - trailer);
        } else {
            ngx_fd_t fd = log->file->fd;
            ngx_write_fd(fd, header, h - header);
            ngx_write_fd(fd, msg.data, msg.len);
            ngx_write_fd(fd, trailer, t - trailer);

            if (fd == ngx_stderr) {
                wrote_stderr = true;
            }
        }
    }

    if (ngx_use_stderr && level <= NGX_LOG_WARN && !wrote_stderr) {
        h = ngx_slprintf(header, last, "nginx: [%s] ", log_levels[level]);
        ngx_write_fd(ngx_stderr, header, h - header);
        ngx_write_fd(ngx_stderr, msg.data, msg.len);
        ngx_write_fd(ngx_stderr, trailer, t - trailer);
    }
}

}  // namespace weserv::nginx
