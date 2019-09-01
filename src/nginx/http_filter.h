#pragma once

extern "C" {
#include <ngx_http.h>
}

namespace weserv {
namespace nginx {

/**
 * Reference: ngx_http_proxy_copy_filter
 */
ngx_int_t ngx_weserv_copy_filter(ngx_event_pipe_t *p, ngx_buf_t *buf);

/**
 * An input filter initialization handler.
 * NGINX calls it when the response body starts arriving and the caller can
 * initialize any state (for example, allocate buffers).
 *
 * The data pointer is provided to NGINX via input_filter_ctx. Below, we
 * set it to the request object.
 */
ngx_int_t ngx_weserv_input_filter_init(void *data);

}  // namespace nginx
}  // namespace weserv
