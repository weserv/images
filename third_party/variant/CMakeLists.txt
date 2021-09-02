# Note: This will not work with GCC 7.3.1 (which is included in devtoolset-7), see:
# https://github.com/mpark/variant/issues/43
FetchContent_Declare(variant
        GIT_REPOSITORY https://github.com/mpark/variant.git
        GIT_TAG 23cb94f
        )

FetchContent_GetProperties(variant)
if (NOT variant_POPULATED)
    FetchContent_Populate(variant)

    add_library(mpark_variant INTERFACE IMPORTED GLOBAL)
    set_target_properties(mpark_variant PROPERTIES
            INTERFACE_INCLUDE_DIRECTORIES "${variant_SOURCE_DIR}/include"
            )
endif()
