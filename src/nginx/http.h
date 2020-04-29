#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/utils/status.h>

#include "module.h"

namespace weserv {
namespace nginx {

/**
 * Sends an HTTP request.
 */
ngx_int_t ngx_weserv_send_http_request(ngx_http_request_t *r,
                                       ngx_weserv_upstream_ctx_t *ctx);

}  // namespace nginx
}  // namespace weserv
