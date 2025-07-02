#pragma once

extern "C" {
#include <ngx_http.h>
}

#include "module.h"

namespace weserv::nginx {

/**
 * Sends an HTTP request.
 */
ngx_int_t ngx_weserv_send_http_request(ngx_http_request_t *r,
                                       ngx_weserv_upstream_ctx_t *ctx);

}  // namespace weserv::nginx
