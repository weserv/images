FetchContent_Declare(echo_nginx_module
        GIT_REPOSITORY https://github.com/openresty/echo-nginx-module.git
        GIT_TAG 2b3b256
        )

FetchContent_GetProperties(echo_nginx_module)
if (NOT echo_nginx_module_POPULATED)
    FetchContent_Populate(echo_nginx_module)
endif()

set(ECHO_MODULE_SOURCE ${echo_nginx_module_SOURCE_DIR} PARENT_SCOPE)
