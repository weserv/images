if (NOT NGX_VERSION)
    set(NGX_VERSION 1.25.4)
endif()

set(NGX_ROOT ${CMAKE_CURRENT_BINARY_DIR})

set(NGX_CONFIGURE_OPTS
        ${CUSTOM_NGX_FLAGS}
        #--with-debug
        "--with-cc=${CMAKE_C_COMPILER}"
        "--with-cpp=${CMAKE_CXX_COMPILER}"
        "--with-cc-opt=${CUSTOM_NGX_CC_OPT}"
        "--with-ld-opt=-Wl,-rpath,${PROJECT_SOURCE_DIR}/lib ${CUSTOM_NGX_LD_OPT}"
        "--add$<$<BOOL:${NGX_DYN_MODULE}>:-dynamic>-module=${PROJECT_SOURCE_DIR}"
        "--add$<$<BOOL:${NGX_DYN_MODULE}>:-dynamic>-module=${RATE_LIMIT_MODULE_SOURCE}"
        --with-file-aio
        --with-http_ssl_module
        --with-http_v2_module
        --with-http_realip_module
        --with-http_stub_status_module
        --with-http_secure_link_module
        --with-pcre-jit
        #--with-google_perftools_module
        )

# Install the echo module only in debug builds
# (needed by the integration tests)
if (CMAKE_BUILD_TYPE MATCHES "Debug")
    list(APPEND NGX_CONFIGURE_OPTS
            "--add$<$<BOOL:${NGX_DYN_MODULE}>:-dynamic>-module=${ECHO_MODULE_SOURCE}"
            )
endif()

if (CMAKE_GENERATOR MATCHES "Make")
    # Use special command for Makefiles to support parallelism.
    set(MAKE_EXE "$(MAKE)")
else()
    set(MAKE_EXE "make")
endif()

ExternalProject_Add(nginx
        DEPENDS ${PROJECT_NAME}
        PREFIX ${NGX_ROOT}
        SOURCE_DIR ${NGX_ROOT}/nginx
        URL https://nginx.org/download/nginx-${NGX_VERSION}.tar.gz
        CONFIGURE_COMMAND ./configure ${NGX_CONFIGURE_OPTS}
        BUILD_COMMAND ${MAKE_EXE}
        BUILD_ALWAYS 1
        BUILD_IN_SOURCE 1
        )
