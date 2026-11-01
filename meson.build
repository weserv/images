project('weserv', 'cpp',
    version: '5.1.2',
    meson_version: '>=0.63',
    license: 'BSD-3-Clause',
    default_options: [
        # We use C++17 features
        'cpp_std=c++17',
        # Do a release (optimized) build by default
        'buildtype=release',
        # Turn off asserts etc. in release mode
        'b_ndebug=if-release',
    ],
)

library_version = meson.project_version()

pkg = import('pkgconfig')

# If we're optimizing (e.g. release mode) we turn off cast checks and g_asserts
if get_option('optimization') not in ['0', 'g']
    add_project_arguments('-DG_DISABLE_CAST_CHECKS', language: 'cpp')
    add_project_arguments('-DG_DISABLE_CHECKS', language: 'cpp')
    add_project_arguments('-DG_DISABLE_ASSERT', language: 'cpp')
endif

cpp = meson.get_compiler('cpp')

if get_option('b_coverage')
    # Use absolute paths to make coverage reports more reliable
    add_project_arguments(cpp.get_supported_arguments('-fprofile-abs-path'), language: 'cpp')
endif

weserv_deps = []
weserv_deps += dependency('vips-cpp', version: '>=8.13')
weserv_deps += cpp.find_library('m', required: false)

summary('Compiler', cpp.get_id(), section: 'Toolchain')
summary('Linker', cpp.get_linker_id(), section: 'Toolchain')

summary('Debugging', get_option('debug'), section: 'Build')
summary('Optimization', get_option('optimization'), section: 'Build')
summary('CLI utility', get_option('cli'), section: 'Build')
summary('Tests', get_option('tests'), section: 'Build')

subdir('include/weserv')
subdir('src/api')
if get_option('cli')
    subdir('src/tools')
endif
if get_option('tests')
    subdir('test/api')
endif
