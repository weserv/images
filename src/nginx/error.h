#pragma once

extern "C" {
#include <ngx_http.h>
}

#include "module.h"

#include <weserv/utils/status.h>

namespace weserv::nginx {

ngx_chain_t *ngx_weserv_error_chain(ngx_http_request_t *r,
                                    ngx_weserv_upstream_ctx_t *upstream_ctx,
                                    const api::utils::Status &status);

}  // namespace weserv::nginx
