#pragma once

extern "C" {
#include <ngx_http.h>
}

#include "module.h"

#include <weserv/io/source_interface.h>
#include <weserv/io/target_interface.h>

namespace weserv {
namespace nginx {

/**
 * The NGINX implementation of io::SourceInterface.
 */
class NgxSource : public api::io::SourceInterface {
 public:
    NgxSource(ngx_chain_t *in) : in_(in), first_in_(in) {}

    ~NgxSource() override = default;

    int64_t read(void *data, size_t length) override;

    int64_t seek(int64_t offset, int whence) override;

 private:
    ngx_chain_t *in_;
    ngx_chain_t *first_in_;

    /* The current read point.
     */
    int64_t read_position_ = 0;
};

/**
 * The NGINX implementation of io::TargetInterface.
 */
class NgxTarget : public api::io::TargetInterface {
 public:
    NgxTarget(ngx_weserv_upstream_ctx_t *upstream_ctx, ngx_http_request_t *r,
              ngx_chain_t **out)
        : upstream_ctx_(upstream_ctx), r_(r), ll_(out), first_ll_(out),
          seek_cl_(*out) {}

    ~NgxTarget() override = default;

    void setup(const std::string &extension) override;

    int64_t write(const void *data, size_t length) override;

    int64_t read(void *data, size_t length) override;

    off_t seek(off_t offset, int whence) override;

    int end() override;

 private:
    ngx_weserv_upstream_ctx_t *upstream_ctx_;
    ngx_http_request_t *r_;
    ngx_chain_t **ll_;
    ngx_chain_t **first_ll_;

    ngx_chain_t *seek_cl_;

    std::string extension_;
    off_t content_length_ = 0;

    /* The current write point.
     */
    int64_t write_position_ = 0;
};

}  // namespace nginx
}  // namespace weserv
