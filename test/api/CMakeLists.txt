option(ENABLE_VALGRIND "Execute test suite with Valgrind" OFF)
option(BUILD_GENERATOR "Build the expected fixtures generator (internal)" OFF)

if (ENABLE_VALGRIND)
    find_program(
            VALGRIND_EXE
            NAMES "valgrind"
            DOC "Path to valgrind executable"
    )
    if (NOT VALGRIND_EXE)
        message(STATUS "valgrind not found")
        set(ENABLE_VALGRIND OFF CACHE BOOL "" FORCE)
    else()
        message(STATUS "Executing test suite with valgrind (${VALGRIND_EXE})")
        set(MEMCHECK_COMMAND "${VALGRIND_EXE} --error-exitcode=1 --leak-check=yes --suppressions=${PROJECT_SOURCE_DIR}/valgrind.suppress")
        separate_arguments(MEMCHECK_COMMAND)
    endif()
endif()

if (ENABLE_COVERAGE)
    message(STATUS "Building test suite with coverage information")

    # From https://github.com/RWTH-HPC/CMake-codecov/blob/master/cmake/FindGcov.cmake
    get_filename_component(COMPILER_PATH "${CMAKE_CXX_COMPILER}" PATH)
    string(REGEX MATCH "^[0-9]+" GCC_VERSION "${CMAKE_CXX_COMPILER_VERSION}")
    find_program(GCOV_BIN NAMES gcov-${GCC_VERSION} gcov HINTS ${COMPILER_PATH})

    find_program(LCOV lcov)
    find_program(GENHTML genhtml)

    # Add target to collect coverage information
    add_custom_target(coverage
            COMMAND ${LCOV} -d "${CMAKE_BINARY_DIR}" -b "${PROJECT_SOURCE_DIR}" -c -o coverage.info --gcov-tool ${GCOV_BIN} --no-external
            COMMAND ${LCOV} -r coverage.info '*/_deps/*' '*/test/api/*' -o coverage.info
            WORKING_DIRECTORY ${PROJECT_BINARY_DIR}
            COMMENT "Collect coverage information using LCOV"
            )

    # Add target to generate HTML coverage report
    add_custom_target(coverage-html
            DEPENDS coverage
            COMMAND ${GENHTML} -t "Images.weserv.nl" -p "${PROJECT_SOURCE_DIR}" --legend --demangle-cpp -o lcov -s coverage.info
            WORKING_DIRECTORY ${PROJECT_BINARY_DIR}
            COMMENT "Generating HTML coverage report lcov/index.html"
            )
endif()

# Catch2 library with the main function to speed up build
add_library(catch2_main OBJECT
        base.cpp
        max_color_distance.h
        max_color_distance.cpp
        similar_image.h
        similar_image.cpp
        test_environment.h
        )

# target_link_libraries on object libraries only works with CMake >= 3.12.
if (${CMAKE_VERSION} VERSION_LESS "3.12.0")
    target_include_directories(catch2_main
            PUBLIC
                ${PROJECT_SOURCE_DIR}/include
                $<TARGET_PROPERTY:Catch2::Catch2,INTERFACE_INCLUDE_DIRECTORIES>
            PRIVATE
                ${VIPS_INCLUDE_DIRS}
            )
else()
    target_include_directories(catch2_main
            PRIVATE
                ${VIPS_INCLUDE_DIRS}
            )
    target_link_libraries(catch2_main
            PUBLIC
                ${PROJECT_NAME}
                Catch2::Catch2
            PRIVATE
                ${VIPS_LDFLAGS}
            )
endif()

file(GLOB_RECURSE files "${CMAKE_CURRENT_SOURCE_DIR}/unit-*.cpp")
foreach (file ${files})
    get_filename_component(file_basename ${file} NAME_WE)
    string(REGEX REPLACE "unit-([^$]+)" "test-\\1" testcase ${file_basename})

    add_executable(${testcase} $<TARGET_OBJECTS:catch2_main> ${file})
    target_compile_definitions(${testcase}
            PRIVATE
                CATCH_CONFIG_FAST_COMPILE
            )
    target_compile_options(${testcase}
            PRIVATE
                $<$<CXX_COMPILER_ID:GNU>:-Wno-deprecated-declarations>
            )
    target_include_directories(${testcase}
            PRIVATE
                ${VIPS_INCLUDE_DIRS}
            )
    target_link_libraries(${testcase}
            PUBLIC
                ${PROJECT_NAME}
            PRIVATE
                ${VIPS_LDFLAGS}
                Catch2::Catch2
            )
    add_test(NAME "${testcase}"
            COMMAND ${testcase}
            WORKING_DIRECTORY ${PROJECT_SOURCE_DIR}
            )

    if (ENABLE_VALGRIND)
        add_test(NAME "${testcase}_valgrind"
                COMMAND ${MEMCHECK_COMMAND} ${CMAKE_RUNTIME_OUTPUT_DIRECTORY}/${testcase}
                WORKING_DIRECTORY ${PROJECT_SOURCE_DIR}
                )
        set_tests_properties("${testcase}_valgrind" PROPERTIES LABELS "valgrind")
    endif()
endforeach()

if (BUILD_GENERATOR)
    add_executable(fixtures-generator generate_expected_fixtures.cpp)
    target_include_directories(fixtures-generator
            PRIVATE
                ${VIPS_INCLUDE_DIRS}
            )
    target_link_libraries(fixtures-generator
            PUBLIC
                ${PROJECT_NAME}
            PRIVATE
                ${VIPS_LDFLAGS}
            )
endif()
