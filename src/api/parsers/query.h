#pragma once

#include "color.h"

#include <cstddef>
#include <stdexcept>
#include <string>
#include <type_traits>
#include <typeindex>
#include <unordered_map>
#include <unordered_set>
#include <vector>

#include <mpark/variant.hpp>
#include <weserv/config.h>

namespace weserv {
namespace api {
namespace parsers {

using TypeMap = std::unordered_map<std::string, std::type_index>;
using SynonymMap = std::unordered_map<std::string, std::string>;
using NginxKeySet = std::unordered_set<std::string>;

class Query {
 public:
    Query(const std::string &value, const Config &config);

    template <typename E,
              typename = typename std::enable_if<std::is_enum<E>::value>::type>
    /**
     * This is the only function that can pass enums,
     * the other functions do not allow this.
     */
    inline E get(const std::string &key, const E &default_val) const {
        // Get the value as an int and call get(), then convert it back to an
        // enum
        auto casted = static_cast<int>(default_val);
        return static_cast<E>(get(key, casted));
    }

    template <typename T,
              typename = typename std::enable_if<!std::is_enum<T>::value>::type>
    const inline T &get(const std::string &key, const T &default_val) const {
        auto it = query_map_.find(key);
        if (it == query_map_.end()) {
            return default_val;
        }
        return mpark::get<T>(it->second);
    }

    template <typename T,
              typename = typename std::enable_if<!std::is_enum<T>::value>::type>
    const inline T &get(const std::string &key) const {
        auto it = query_map_.find(key);
        if (it == query_map_.end()) {  // LCOV_EXCL_START
            throw std::logic_error("Reached a supposed unreachable point");
        }
        // LCOV_EXCL_STOP

        return mpark::get<T>(it->second);
    }

    template <typename T,
              typename = typename std::enable_if<!std::is_enum<T>::value>::type,
              class Predicate>
    const inline T &get_if(const std::string &key, Predicate predicate,
                           const T &default_val) const {
        auto it = query_map_.find(key);
        if (it == query_map_.end()) {
            return default_val;
        }
        const T &val = mpark::get<T>(it->second);
        return predicate(val) ? val : default_val;
    }

    inline bool exists(const std::string &key) {
        return query_map_.find(key) != query_map_.end();
    }

    template <typename T,
              typename = typename std::enable_if<!std::is_enum<T>::value>::type>
    inline void update(const std::string &key, const T &val) {
        query_map_[key] = val;
    }

 private:
    using QueryVariant = mpark::variant<bool, int, float, Color,
                                        std::vector<int>, std::vector<float>>;
    std::unordered_map<std::string, QueryVariant> query_map_;

    const Config &config_;

    template <typename T>
    std::vector<T> tokenize(const std::string &data,
                            const std::string &delimiters, size_t max_items);

    inline void add_value(const std::string &key, const std::string &value,
                          std::type_index type);
};

}  // namespace parsers
}  // namespace api
}  // namespace weserv
