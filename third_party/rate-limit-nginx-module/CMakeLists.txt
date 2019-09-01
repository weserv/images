FetchContent_Declare(rate_limit_nginx_module
        GIT_REPOSITORY https://github.com/weserv/rate-limit-nginx-module.git
        )

FetchContent_GetProperties(rate_limit_nginx_module)
if (NOT rate_limit_nginx_module_POPULATED)
    FetchContent_Populate(rate_limit_nginx_module)
endif()

set(RATE_LIMIT_MODULE_SOURCE ${rate_limit_nginx_module_SOURCE_DIR} PARENT_SCOPE)
