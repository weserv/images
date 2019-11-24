if (NOT NGX_VERSION)
    set(NGX_VERSION 1.17.6)
endif()

set(NGX_ROOT ${CMAKE_CURRENT_BINARY_DIR})

list(APPEND NGX_CONFIGURE_OPTS
        #--with-debug
        "--with-cc=${CMAKE_C_COMPILER}"
        "--with-cpp=${CMAKE_CXX_COMPILER}"
        "--with-cc-opt=-I${PROJECT_SOURCE_DIR}/include ${CUSTOM_NGX_CC_OPT}"
        "--with-ld-opt=-L${PROJECT_SOURCE_DIR}/lib -Wl,-rpath,${PROJECT_SOURCE_DIR}/lib ${CUSTOM_NGX_LD_OPT}"
        )

if (NGX_DYN_MODULE)
    list(APPEND NGX_CONFIGURE_OPTS
            "--add-dynamic-module=${PROJECT_SOURCE_DIR}"
            )
else()
    list(APPEND NGX_CONFIGURE_OPTS
            "--add-module=${PROJECT_SOURCE_DIR}"
            )
endif()

list(APPEND NGX_CONFIGURE_OPTS
        ${CUSTOM_NGX_FLAGS}
        --with-file-aio
        --with-http_ssl_module
        --with-http_v2_module
        --with-http_realip_module
        --with-http_stub_status_module
        --with-pcre-jit
        #--with-google_perftools_module
        "--add-module=${RATE_LIMIT_MODULE_SOURCE}"
        )

# Install the echo module only in debug builds 
# (needed by the integration tests)
if (CMAKE_BUILD_TYPE MATCHES "Debug")
    list(APPEND NGX_CONFIGURE_OPTS
            "--add-module=${ECHO_MODULE_SOURCE}"
            )
endif()

ExternalProject_Add(nginx
        DEPENDS ${PROJECT_NAME}
        PREFIX ${NGX_ROOT}
        SOURCE_DIR ${NGX_ROOT}/nginx
        URL http://nginx.org/download/nginx-${NGX_VERSION}.tar.gz
        CONFIGURE_COMMAND ./configure ${NGX_CONFIGURE_OPTS}
        BUILD_COMMAND $(MAKE)
        BUILD_ALWAYS 1
        BUILD_IN_SOURCE 1
        )
