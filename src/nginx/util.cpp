#include "util.h"

namespace weserv {
namespace nginx {

ngx_int_t ngx_str_copy_from_std(ngx_pool_t *pool, const std::string &src,
                                ngx_str_t *dst) {
    auto *data = reinterpret_cast<u_char *>(ngx_pcalloc(pool, src.size() + 1));
    if (!data) {
        return NGX_ERROR;
    }
    ngx_memcpy(data, src.data(), src.size());
    dst->data = data;
    dst->len = src.length();
    return NGX_OK;
}

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

ngx_int_t get_content_disposition(ngx_http_request_t *r,
                                  const std::string &extension,
                                  ngx_str_t *output) {
    bool is_valid = false;

    ngx_str_t filename;
    if (ngx_http_arg(r, (u_char *)"filename", 8, &filename) == NGX_OK) {
        // https://tools.ietf.org/html/rfc2183
        is_valid = filename.len != 0 && filename.len <= 78 &&
                   std::all_of(filename.data, filename.data + filename.len,
                               [](u_char c) { return std::isalnum(c); });
    }

    if (!is_valid) {
        filename = ngx_string("image");
    }

    size_t prefix_size = sizeof("inline; filename=") - 1;

    output->data = reinterpret_cast<u_char *>(
        ngx_pnalloc(r->pool, prefix_size + filename.len + extension.size()));
    if (output->data == nullptr) {
        return NGX_ERROR;
    }

    u_char *o = ngx_cpymem(output->data, "inline; filename=", prefix_size);
    output->len = prefix_size;

    o = ngx_cpymem(o, filename.data, filename.len);
    output->len += filename.len;

    o = ngx_cpymem(o, extension.data(), extension.size());
    output->len += extension.length();

    return NGX_OK;
}

time_t parse_max_age(ngx_str_t &s) {
    time_t max_age = ngx_parse_time(&s, 1);
    if (max_age == static_cast<time_t>(NGX_ERROR)) {
        return NGX_ERROR;
    }

    switch (max_age) {
        case 60 * 60 * 24 * 31:      // 1 month
        case 60 * 60 * 24 * 31 * 2:  // 2 months
        case 60 * 60 * 24 * 31 * 3:  // 3 months
        case 60 * 60 * 24 * 31 * 6:  // 6 months
        case 60 * 60 * 24 * 365:     // 1 year
            return max_age;
        default:
            return NGX_ERROR;
    }
}

ngx_str_t extension_to_mime_type(const std::string &extension) {
    if (extension == ".jpg") {
        return ngx_string("image/jpeg");
    } else if (extension == ".png") {
        return ngx_string("image/png");
    } else if (extension == ".webp") {
        return ngx_string("image/webp");
    } else if (extension == ".tiff") {
        return ngx_string("image/tiff");
    } else if (extension == ".gif") {
        return ngx_string("image/gif");
    } else { /*if (extension == ".json")*/
        return ngx_string("application/json");
    }
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

    bool debug_connection = !!(log->log_level & NGX_LOG_DEBUG_CONNECTION);
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

}  // namespace nginx
}  // namespace weserv
