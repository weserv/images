#pragma once

#include <string>

namespace weserv::api::parsers {

template <typename T>
T parse(const std::string &value);

}  // namespace weserv::api::parsers
