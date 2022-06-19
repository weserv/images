#include "environment.h"

#include "util.h"

namespace weserv::nginx {

void NgxEnvironment::log(LogLevel level, const char *message) {
    ngx_uint_t ngx_level;
    switch (level) {
        case LogLevel::Debug:
            ngx_level = NGX_LOG_DEBUG;
            break;
        case LogLevel::Info:
            ngx_level = NGX_LOG_INFO;
            break;
        case LogLevel::Warning:
            ngx_level = NGX_LOG_WARN;
            break;
        case LogLevel::Error:
        default:
            ngx_level = NGX_LOG_ERR;
            break;
    }

    ngx_str_t msg = {strlen(message),
                     reinterpret_cast<u_char *>(const_cast<char *>(message))};
    ngx_weserv_log(log_, ngx_level, msg);
}

}  // namespace weserv::nginx
