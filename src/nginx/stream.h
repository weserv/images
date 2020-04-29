#pragma once

extern "C" {
#include <ngx_http.h>
}

#include <weserv/io/source_interface.h>
#include <weserv/io/target_interface.h>

namespace weserv {
namespace nginx {

/**
 * The nginx implementation of io::SourceInterface.
 */
class NgxSource : public api::io::SourceInterface {
 public:
    NgxSource(ngx_http_request_t *r, ngx_chain_t *in) : r_(r), in_(in) {}

    ~NgxSource() override = default;

    int64_t read(void *data, size_t length) override;

    int64_t seek(int64_t /* unsused */, int /* unsused */) override {
        // nginx sources are not seekable
        return -1;
    }

 private:
    ngx_http_request_t *r_;
    ngx_chain_t *in_;
};

/**
 * The nginx implementation of io::TargetInterface.
 */
class NgxTarget : public api::io::TargetInterface {
 public:
    NgxTarget(ngx_http_request_t *r, ngx_chain_t **out) : r_(r), ll_(out) {}

    ~NgxTarget() override = default;

    void setup(const std::string &extension) override;

    int64_t write(const void *data, size_t length) override;

    void finish() override;

 private:
    ngx_http_request_t *r_;
    ngx_chain_t **ll_;

    std::string extension_;
    off_t content_length_ = 0;
};

}  // namespace nginx
}  // namespace weserv
