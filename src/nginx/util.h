#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <ctime>
#include <string>

namespace weserv::nginx {

/**
 * Convert a ngx_str_t to std::string.
 */
std::string ngx_str_to_std(const ngx_str_t &src);

/**
 * Returns true if the given URL starts with a valid http(s) scheme.
 * If so, we consider it to be an absolute URL.
 */
bool has_valid_scheme(const ngx_str_t &url);

/**
 * Is base64 output needed?
 */
bool is_base64_needed(ngx_http_request_t *r);

/**
 * Converts an entire output chain to base64.
 */
ngx_chain_t *output_chain_to_base64(ngx_http_request_t *r, ngx_chain_t *in);

/**
 * Get the Content-Disposition response header.
 */
ngx_int_t get_content_disposition(ngx_http_request_t *r,
                                  const std::string &extension,
                                  ngx_str_t *output);

/**
 * Parse the value given within the &maxage= query.
 */
time_t parse_max_age(ngx_str_t &max_age);

/**
 * Determines the appropriate mime type using the provided extension.
 */
ngx_str_t extension_to_mime_type(const std::string &extension);

/**
 * Compare two ngx_str_t strings.
 */
inline bool ngx_string_equal(const ngx_str_t &str1, const ngx_str_t &str2) {
    return str1.len == str2.len && !ngx_strncmp(str1.data, str2.data, str1.len);
}

/**
 * Log a message with a specific log level.
 * Note: this function allows you to log percent signs (for e.g. URL-encoded
 * strings). Use ngx_log_error_core for a log method that uses printf format
 * placeholders.
 * Reference: ngx_log_error_core
 */
void ngx_weserv_log(ngx_log_t *log, ngx_uint_t level, ngx_str_t msg);

}  // namespace weserv::nginx
