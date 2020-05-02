#pragma once

#include <stdexcept>
#include <string>

namespace weserv {
namespace api {
namespace parsers {

template <>
inline int parse<int>(const std::string &value) {
    int result = std::stoi(value);
    if (result > VIPS_MAX_COORD) {
        throw std::out_of_range("parse<int>(): value is out of range");
    }
    return result;
}

template <>
inline float parse<float>(const std::string &value) {
    float result = std::stof(value);
    if (result > VIPS_MAX_COORD) {
        throw std::out_of_range("parse<float>(): value is out of range");
    }
    return result;
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
