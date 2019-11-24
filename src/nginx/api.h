#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/utils/status.h>

#include "module.h"

#include <string>

namespace weserv {
namespace nginx {

ngx_int_t ngx_weserv_return_error(ngx_http_request_t *r,
                                  api::utils::Status status, ngx_chain_t *out);

ngx_int_t ngx_weserv_process(ngx_http_request_t *r,
                             ngx_weserv_filter_ctx_t *ctx, ngx_chain_t *out);

}  // namespace nginx
}  // namespace weserv
