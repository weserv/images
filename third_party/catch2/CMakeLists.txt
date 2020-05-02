FetchContent_Declare(catch2
        GIT_REPOSITORY https://github.com/catchorg/Catch2.git
        GIT_TAG v2.12.1
        )

FetchContent_GetProperties(catch2)
if (NOT catch2_POPULATED)
    FetchContent_Populate(catch2)

    add_library(Catch2::Catch2 INTERFACE IMPORTED GLOBAL)
    set_target_properties(Catch2::Catch2 PROPERTIES
            INTERFACE_INCLUDE_DIRECTORIES "${catch2_SOURCE_DIR}/single_include"
            )
endif()
