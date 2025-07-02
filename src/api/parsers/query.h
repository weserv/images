#pragma once

#include "color.h"
#include "coordinate.h"

#include <cstddef>
#include <string>
#include <type_traits>
#include <typeindex>
#include <unordered_map>
#include <unordered_set>
#include <variant>
#include <vector>

namespace weserv::api::parsers {

using TypeMap = std::unordered_map<std::string, std::type_index>;
using SynonymMap = std::unordered_map<std::string, std::string>;
using NginxKeySet = std::unordered_set<std::string>;

class Query {
 public:
    explicit Query(const std::string &value);

    template <typename E, typename = std::enable_if_t<std::is_enum_v<E>>>
    /**
     * This is the only function that can pass enums, the other functions do not
     * allow this.
     */
    inline E get(const std::string &key, const E &default_val) const {
        // Get the value as an int and call get(), then convert it back to an
        // enum
        auto casted = static_cast<int>(default_val);
        return static_cast<E>(get(key, casted));
    }

    template <typename T, typename = std::enable_if_t<!std::is_enum_v<T>>>
    const inline T &get(const std::string &key, const T &default_val) const {
        auto it = query_map_.find(key);
        return it != query_map_.end() ? std::get<T>(it->second) : default_val;
    }

    template <typename T, typename = std::enable_if_t<!std::is_enum_v<T>>>
    const inline T &get(const std::string &key) const {
        const auto &val = query_map_.at(key);
        return std::get<T>(val);
    }

    template <typename T, typename = std::enable_if_t<!std::is_enum_v<T>>,
              typename Predicate>
    const inline T &get_if(const std::string &key, Predicate predicate,
                           const T &default_val) const {
        auto it = query_map_.find(key);
        if (it == query_map_.end()) {
            return default_val;
        }
        const T &val = std::get<T>(it->second);
        return predicate(val) ? val : default_val;
    }

    inline bool exists(const std::string &key) {
        return query_map_.find(key) != query_map_.end();
    }

    template <typename T, typename = std::enable_if_t<!std::is_enum_v<T>>>
    inline void update(const std::string &key, const T &val) {
        query_map_[key] = val;
    }

 private:
    using QueryVariant = std::variant<bool, int, float, Color, Coordinate,
                                      std::vector<int>, std::vector<float>>;
    std::unordered_map<std::string, QueryVariant> query_map_;

    template <typename T>
    std::vector<T> tokenize(const std::string &data,
                            const std::string &delimiters, size_t max_items);

    inline void add_value(const std::string &key, const std::string &value,
                          std::type_index type);
};

}  // namespace weserv::api::parsers
