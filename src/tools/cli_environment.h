#pragma once

#include <ctime>
#include <iomanip>
#include <iostream>

#include <weserv/env_interface.h>

/**
 * The CLI implementation of ApiEnvInterface.
 */
class CliEnvironment : public weserv::api::ApiEnvInterface {
 public:
    CliEnvironment() = default;

    ~CliEnvironment() override = default;

    void log(LogLevel level, const char *message) override {
        std::string str_level;
        switch (level) {
            case LogLevel::Debug:
                str_level = "debug";
                break;
            case LogLevel::Info:
                str_level = "info";
                break;
            case LogLevel::Warning:
                str_level = "warning";
                break;
            case LogLevel::Error:
            default:
                str_level = "error";
                break;
        }

        auto now = std::time(nullptr);
        std::cout << std::put_time(std::localtime(&now), "%Y/%m/%d %H:%M:%S")
                  << " [" << str_level << "]: " << message << std::endl;
    }
};
