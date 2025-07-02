#pragma once

extern "C" {
#include <ngx_core.h>
}

#include <weserv/env_interface.h>

namespace weserv::nginx {

/**
 * The NGINX implementation of ApiEnvInterface.
 */
class NgxEnvironment : public api::ApiEnvInterface {
 public:
    explicit NgxEnvironment(ngx_log_t *log) : log_(log) {}

    ~NgxEnvironment() override = default;

    void log(LogLevel level, const char *message) override;

 private:
    ngx_log_t *log_;
};

}  // namespace weserv::nginx
