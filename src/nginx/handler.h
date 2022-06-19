#pragma once

extern "C" {
#include <ngx_http.h>
}

namespace weserv::nginx {

/**
 * The request handler for locations configured with weserv.
 */
ngx_int_t ngx_weserv_request_handler(ngx_http_request_t *r);

}  // namespace weserv::nginx
