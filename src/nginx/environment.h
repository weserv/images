#pragma once

extern "C" {
#include <ngx_core.h>
}

#include <weserv/env_interface.h>

namespace weserv {
namespace nginx {

/**
 * The nginx implementation of ApiEnvInterface.
 */
class NgxEnvironment : public api::ApiEnvInterface {
 public:
    NgxEnvironment(ngx_log_t *log) : log_(log) {}

    ~NgxEnvironment() override = default;

    void log(LogLevel level, const char *message) override;

 private:
    ngx_log_t *log_;
};

}  // namespace nginx
}  // namespace weserv
