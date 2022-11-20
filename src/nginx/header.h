#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <ctime>
#include <string>

namespace weserv::nginx {

/**
 * Reference: ngx_http_set_expires
 */
ngx_int_t set_expires_header(ngx_http_request_t *r, time_t max_age);

ngx_int_t set_content_disposition_header(ngx_http_request_t *r,
                                         const std::string &extension);

/**
 * Reference: ngx_http_dav_location
 */
ngx_int_t set_location_header(ngx_http_request_t *r, ngx_str_t *value);

ngx_int_t set_link_header(ngx_http_request_t *r, const ngx_str_t &url);

}  // namespace weserv::nginx
