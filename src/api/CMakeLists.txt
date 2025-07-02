set(HEADERS
        exceptions/invalid.h
        exceptions/large.h
        exceptions/unreadable.h
        exceptions/unsupported.h
        io/blob.h
        io/source.h
        io/target.h
        parsers/color.h
        parsers/coordinate.h
        parsers/enumeration.h
        parsers/numeric.h
        parsers/query.h
        processors/alignment.h
        processors/background.h
        processors/base.h
        processors/blur.h
        processors/brightness.h
        processors/contrast.h
        processors/crop.h
        processors/embed.h
        processors/filter.h
        processors/gamma.h
        processors/mask.h
        processors/modulate.h
        processors/orientation.h
        processors/rotation.h
        processors/sharpen.h
        processors/stream.h
        processors/thumbnail.h
        processors/tint.h
        processors/trim.h
        utils/utility.h
        api_manager_impl.h
        enums.h
        )

set(SOURCES
        parsers/color.cpp
        parsers/coordinate.cpp
        parsers/query.cpp
        io/source.cpp
        io/target.cpp
        processors/alignment.cpp
        processors/background.cpp
        processors/blur.cpp
        processors/brightness.cpp
        processors/contrast.cpp
        processors/crop.cpp
        processors/embed.cpp
        processors/filter.cpp
        processors/gamma.cpp
        processors/mask.cpp
        processors/modulate.cpp
        processors/orientation.cpp
        processors/rotation.cpp
        processors/sharpen.cpp
        processors/stream.cpp
        processors/thumbnail.cpp
        processors/tint.cpp
        processors/trim.cpp
        utils/status.cpp
        api_manager_impl.cpp
        )

add_library(${PROJECT_NAME} SHARED ${HEADERS} ${SOURCES})

target_include_directories(${PROJECT_NAME}
        PUBLIC
            $<BUILD_INTERFACE:${PROJECT_SOURCE_DIR}/include>
            $<INSTALL_INTERFACE:include>
        PRIVATE
            ${VIPS_INCLUDE_DIRS}
        )

target_link_libraries(${PROJECT_NAME}
        PRIVATE
            ${VIPS_LDFLAGS}
        )

set_target_properties(${PROJECT_NAME}
        PROPERTIES
            VERSION ${PROJECT_VERSION}
            SOVERSION ${PROJECT_VERSION_MAJOR}
        )

install(TARGETS ${PROJECT_NAME}
        LIBRARY DESTINATION ${CMAKE_INSTALL_LIBDIR}
        COMPONENT ${PROJECT_NAME}
        )

install(DIRECTORY ${PROJECT_SOURCE_DIR}/include/
        DESTINATION ${CMAKE_INSTALL_INCLUDEDIR}
        FILES_MATCHING PATTERN "*.h"
        )

# Add a pkg-config file
configure_file("${CMAKE_CURRENT_SOURCE_DIR}/weserv.pc.in"
        "weserv.pc" @ONLY
        )

install(FILES "${CMAKE_CURRENT_BINARY_DIR}/weserv.pc"
        DESTINATION "${CMAKE_INSTALL_LIBDIR}/pkgconfig"
        )
