#pragma once

extern "C" {
#include <ngx_http.h>
}

namespace weserv::nginx {

/**
 * Concatenate a relative URL to a base URL making it absolute.
 */
ngx_int_t concat_url(ngx_pool_t *pool, const ngx_str_t &base,
                     const ngx_str_t &relative, ngx_str_t *output);

/**
 * Attempts to parse a escaped-URI based on RFC 3986.
 */
ngx_int_t parse_url(ngx_pool_t *pool, ngx_str_t &uri, ngx_str_t *output);

}  // namespace weserv::nginx
