# Note: The FetchContent module was added in CMake 3.11
cmake_minimum_required(VERSION 3.11)

project(imagesweserv
        VERSION 5.0.0
        DESCRIPTION "Image cache and resize service"
        LANGUAGES
            C
            CXX
        )

# Set output directories in which to build the target files
set(CMAKE_ARCHIVE_OUTPUT_DIRECTORY ${PROJECT_SOURCE_DIR}/lib)
set(CMAKE_LIBRARY_OUTPUT_DIRECTORY ${PROJECT_SOURCE_DIR}/lib)
set(CMAKE_RUNTIME_OUTPUT_DIRECTORY ${PROJECT_SOURCE_DIR}/bin)

# Options
option(ENABLE_COVERAGE "Compile in coverage mode" OFF)
option(ENABLE_SANITIZER "Build with clang sanitizer" OFF)
option(ENABLE_CLANG_TIDY "Enable source code checking using clang-tidy" OFF)
option(BUILD_TOOLS "Whether or not to build the tools" OFF)
option(BUILD_TESTS "Whether or not to build the tests" OFF)
option(INSTALL_NGX_MODULE "Install nginx along with the imagesweserv module" ON)

# Set a default build type if none was specified
if (NOT CMAKE_BUILD_TYPE AND NOT CMAKE_CONFIGURATION_TYPES)
    message(STATUS "Setting build type to 'Release' as none was specified.")
    set(CMAKE_BUILD_TYPE Release CACHE STRING "Choose the type of build." FORCE)
endif()

# Let's enable C++11
set(CMAKE_CXX_STANDARD 11)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Coverage flags
if (ENABLE_COVERAGE)
    set(CMAKE_CXX_FLAGS_DEBUG "-g -O0 -fprofile-arcs -ftest-coverage")
    set(CMAKE_EXE_LINKER_FLAGS "-lgcov --coverage")
elseif (CMAKE_COMPILER_IS_GNUCXX)
    # Compiler flags
    set(CMAKE_CXX_FLAGS_RELEASE "-O3 -ffast-math") # Optimize for performance
    set(CMAKE_EXE_LINKER_FLAGS "-s")   # Strip binary
endif()

# AddressSanitizer flags
if (ENABLE_SANITIZER)
    set(CMAKE_CXX_FLAGS_DEBUG "${CMAKE_CXX_FLAGS_DEBUG} -fsanitize=address -fno-omit-frame-pointer -g -O1")
    set(CMAKE_LINKER_FLAGS_DEBUG "${CMAKE_LINKER_FLAGS_DEBUG} -fsanitize=address -fno-omit-frame-pointer -g -O1")
endif()

if (ENABLE_CLANG_TIDY)
    find_program(
            CLANG_TIDY_EXE
            NAMES "clang-tidy"
            DOC "Path to clang-tidy executable"
    )
    if (NOT CLANG_TIDY_EXE)
        message(STATUS "clang-tidy not found")
        set(ENABLE_CLANG_TIDY OFF CACHE BOOL "" FORCE)
    else()
        set(CMAKE_CXX_CLANG_TIDY "${CLANG_TIDY_EXE}" "-format-style=file")
    endif()
endif()

include(ExternalProject)
include(FetchContent)

# Use GNUInstallDirs to install libraries into correct locations on all platforms
include(GNUInstallDirs)

# Find Catch2 (optional)
find_package(Catch2 2.7.1 QUIET)

# Find mpark/variant (optional)
# TODO(kleisauke): Can't set 1.4.0 as minimum version, see: https://github.com/mpark/variant/issues/60
find_package(mpark_variant 1.3.0 QUIET)

# Find libvips (required)
find_package(PkgConfig)
pkg_check_modules(VIPS vips-cpp>=8.8 REQUIRED)

# Build mpark/variant (an implementation of C++17 std::variant for C++11/14/17), if necessary
if (NOT mpark_variant_FOUND)
    add_subdirectory(third_party/variant)
endif()

# Create a shared imagesweserv library
add_subdirectory(src/api)

if (BUILD_TOOLS)
    add_subdirectory(src/tools)
endif()

if (BUILD_TESTS)
    enable_testing()

    # Build Catch2 (a header-only test framework), if necessary
    if (NOT Catch2_FOUND)
        add_subdirectory(third_party/catch2)
    endif()

    add_subdirectory(test/api)
endif()

# Install nginx along with the nginx weserv module, if necessary
if (INSTALL_NGX_MODULE)
    add_subdirectory(third_party/rate-limit-nginx-module)

    # Install the echo module only in debug builds
    # (needed by the integration tests)
    if (CMAKE_BUILD_TYPE MATCHES "Debug")
        add_subdirectory(third_party/echo-nginx-module)
    endif()

    add_subdirectory(third_party/nginx)
endif()
