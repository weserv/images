#pragma once

#include <string>

namespace weserv {
namespace api {
namespace parsers {

template <typename T>
T parse(const std::string &value);

}  // namespace parsers
}  // namespace api
}  // namespace weserv
