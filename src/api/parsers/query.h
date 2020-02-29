#pragma once

#include "parsers/base.h"
#include "parsers/color.h"
#include "parsers/enumeration.h"
#include "parsers/numeric.h"
#include "parsers/query_holder.h"
#include "utils/utility.h"

#include <memory>
#include <string>
#include <typeindex>
#include <unordered_map>
#include <vector>

#include <mpark/variant.hpp>
#include <vips/vips8>

namespace weserv {
namespace api {
namespace parsers {

using TypeMap = std::unordered_map<std::string, std::type_index>;
using SynonymMap = std::unordered_map<std::string, std::string>;
using QueryHolderPtr = std::shared_ptr<QueryHolder>;

template <typename T>
inline std::vector<T> tokenize(const std::string &data,
                               const std::string &delimiters,
                               typename std::vector<T>::size_type max_items);

inline void add_value(QueryMap &map, const std::string &key,
                      const std::string &value, std::type_index type);

template <>
QueryHolderPtr parse(const std::string &value);

}  // namespace parsers
}  // namespace api
}  // namespace weserv
