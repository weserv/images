add_executable(${PROJECT_NAME}-cli cli.cpp cli_environment.h)

target_link_libraries(${PROJECT_NAME}-cli
        PRIVATE
            ${PROJECT_NAME}
        )

install(TARGETS ${PROJECT_NAME}-cli
        RUNTIME DESTINATION ${CMAKE_INSTALL_BINDIR}
        COMPONENT ${PROJECT_NAME}-cli
        )
