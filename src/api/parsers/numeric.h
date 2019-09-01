#pragma once

#include <stdexcept>
#include <string>

namespace weserv {
namespace api {
namespace parsers {

// `&w=VIPS_MAX_COORD`
constexpr size_t MAX_VALUE_LENGTH = sizeof("10000000") - 1;

template <>
inline int parse<int>(const std::string &value) {
    if (value.size() > MAX_VALUE_LENGTH) {
        throw std::out_of_range("parse<int>(): value is out of range");
    }

    return std::stoi(value);
}

template <>
inline float parse<float>(const std::string &value) {
    if (value.size() > MAX_VALUE_LENGTH) {
        throw std::out_of_range("parse<float>(): value is out of range");
    }

    return std::stof(value);
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
