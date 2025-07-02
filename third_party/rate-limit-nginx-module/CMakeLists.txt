FetchContent_Declare(rate_limit_nginx_module
        GIT_REPOSITORY https://github.com/weserv/rate-limit-nginx-module.git
        GIT_TAG v1.0.0
        )

FetchContent_MakeAvailable(rate_limit_nginx_module)

set(RATE_LIMIT_MODULE_SOURCE ${rate_limit_nginx_module_SOURCE_DIR} PARENT_SCOPE)
