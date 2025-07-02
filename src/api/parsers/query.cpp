#include "query.h"

#include "base.h"
#include "enumeration.h"
#include "numeric.h"

#include <weserv/enums.h>

namespace weserv::api::parsers {

using enums::Canvas;
using enums::FilterType;
using enums::MaskType;
using enums::Output;
using enums::Position;

// `&[lossless]=true`
constexpr size_t MAX_KEY_LENGTH = sizeof("lossless") - 1;

// A vector must not have more than 10000 elements.
constexpr size_t MAX_VECTOR_SIZE = 10000;

// Note: We check crazy numbers within `numeric.h`

// clang-format off
const TypeMap &type_map = {
    {"w",       typeid(Coordinate)},
    {"h",       typeid(Coordinate)},
    {"dpr",     typeid(float)},
    {"fit",     typeid(Canvas)},
    {"we",      typeid(bool)},
    {"crop",    typeid(std::vector<int>)},  // Deprecated
    {"cx",      typeid(Coordinate)},
    {"cy",      typeid(Coordinate)},
    {"cw",      typeid(Coordinate)},
    {"ch",      typeid(Coordinate)},
    {"precrop", typeid(bool)},
    {"a",       typeid(Position)},
    {"fpx",     typeid(float)},
    {"fpy",     typeid(float)},
    {"mask",    typeid(MaskType)},
    {"mtrim",   typeid(bool)},
    {"mbg",     typeid(Color)},
    {"ro",      typeid(int)},
    {"flip",    typeid(bool)},
    {"flop",    typeid(bool)},
    {"bri",     typeid(int)},
    {"mod",     typeid(std::vector<float>)},
    {"sat",     typeid(float)},
    {"hue",     typeid(int)},
    {"con",     typeid(int)},
    {"gam",     typeid(float)},
    {"sharp",   typeid(std::vector<float>)},
    {"sharpf",  typeid(float)},
    {"sharpj",  typeid(float)},
    {"trim",    typeid(int)},
    {"blur",    typeid(float)},
    {"filt",    typeid(FilterType)},
    {"start",   typeid(Color)},
    {"stop",    typeid(Color)},
    {"bg",      typeid(Color)},
    {"cbg",     typeid(Color)},
    {"rbg",     typeid(Color)},
    {"tint",    typeid(Color)},
    {"q",       typeid(int)},
    {"l",       typeid(int)},
    {"output",  typeid(Output)},
    {"il",      typeid(bool)},
    {"ll",      typeid(bool)},              // TODO(kleisauke): Documentation needed.
    {"af",      typeid(bool)},
    {"page",    typeid(int)},
    {"n",       typeid(int)},
    {"loop",    typeid(int)},               // TODO(kleisauke): Documentation needed.
    {"delay",   typeid(std::vector<int>)},  // TODO(kleisauke): Documentation needed.
    {"fsol",    typeid(bool)},              // TODO(kleisauke): Documentation needed.
};

const SynonymMap &synonym_map = {
    {"shape",    "mask"},   // &shape= was deprecated since API version 4
    {"strim",    "mtrim"},  // &strim= was deprecated since API version 4
    {"or",       "ro"},     // &or= was deprecated since API version 5
    {"t",        "fit"},    // &t= was deprecated since API version 5
    // TODO(kleisauke): Synonym this within a major release (since it breaks BC).
    //{"bri",      "mod"},
    // Some handy synonyms
    {"pages",    "n"},
    {"width",    "w"},
    {"height",   "h"},
    {"align",    "a"},
    {"level",    "l"},
    {"quality",  "q"},
    {"lossless", "ll"},
};

const NginxKeySet &nginx_keys = {
    "url",
    "default",
    "errorredirect",  // Deprecated
    "filename",
    "encoding",
    "maxage",
};
// clang-format on

template <typename T>
std::vector<T> Query::tokenize(const std::string &data,
                               const std::string &delimiters,
                               size_t max_items) {
    // Skip delimiters at beginning
    size_t last_pos = data.find_first_not_of(delimiters, 0);

    // Find first non-delimiter
    size_t pos = data.find_first_of(delimiters, last_pos);

    std::vector<T> vector;
    vector.reserve(max_items);

    size_t i = 0;
    while (pos != std::string::npos || last_pos != std::string::npos) {
        try {
            // Found a token, add it to the vector
            vector.push_back(parse<T>(data.substr(last_pos, pos - last_pos)));
        } catch (...) {
            // -1 by default
            vector.push_back(static_cast<T>(-1));
        }

        if (++i >= max_items) {
            break;
        }

        // Skip delimiters
        last_pos = data.find_first_not_of(delimiters, pos);

        // Find next non-delimiter
        pos = data.find_first_of(delimiters, last_pos);
    }

    return vector;
}

void Query::add_value(const std::string &key, const std::string &value,
                      std::type_index type) {
    if (type == typeid(bool)) {
        // Only emplace `false` if it's explicitly specified because we
        // interpret empty strings (for e.g. `&we`) as `true`
        query_map_.emplace(key, value != "false" && value != "0");
    } else if (type == typeid(int)) {
        try {
            query_map_.emplace(key, parse<int>(value));
        } catch (...) {
            // -1 by default
            query_map_.emplace(key, -1);
        }
    } else if (type == typeid(float)) {
        try {
            query_map_.emplace(key, parse<float>(value));
        } catch (...) {
            // -1.0 by default
            query_map_.emplace(key, -1.0F);
        }
    } else if (type == typeid(Coordinate)) {
        query_map_.emplace(key, parse<Coordinate>(value));
    } else if (type == typeid(Position)) {
        auto position = parse<Position>(value);

        // Deprecated &a=focal-x%-y%
        size_t pos;
        if (position == Position::Focal &&
            (pos = value.find_first_of('-')) != std::string::npos) {
            // Center on default
            std::vector<float> focal = {0.5F, 0.5F};

            auto values = value.substr(pos + 1);
            auto params = tokenize<int>(values, "-", 2);

            for (size_t i = 0; i != params.size(); ++i) {
                // A single percentage needs to be in the range of 0 - 100
                if (params[i] >= 0 && params[i] <= 100) {
                    focal[i] = params[i] / 100.0F;
                }
            }

            query_map_.emplace("fpx", focal[0]);
            query_map_.emplace("fpy", focal[1]);
        }

        query_map_.emplace(key, static_cast<int>(position));
    } else if (type == typeid(FilterType)) {
        query_map_.emplace(key, static_cast<int>(parse<FilterType>(value)));
    } else if (type == typeid(MaskType)) {
        query_map_.emplace(key, static_cast<int>(parse<MaskType>(value)));
    } else if (type == typeid(Output)) {
        query_map_.emplace(key, static_cast<int>(parse<Output>(value)));
    } else if (type == typeid(Canvas)) {
        // Deprecated without enlargement parameters
        if (value == "fit" || value == "squaredown") {
            query_map_.emplace("we", true);
        }

        query_map_.emplace(key, static_cast<int>(parse<Canvas>(value)));
    } else if (type == typeid(Color)) {
        query_map_.emplace(key, parse<Color>(value));
    } else if (key == "delay") {  // type == typeid(std::vector<int>)
        auto delays = tokenize<int>(value, ",", MAX_VECTOR_SIZE);
        query_map_.emplace(key, delays);
    } else if (key == "sharp") {  // type == typeid(std::vector<float>)
        auto params = tokenize<float>(value, ",", 3);

        if (params.size() == 1) {
            // Assume sigma if only 1 value is given (e.g. &sharp=5)
            query_map_.emplace(key, params[0]);
        } else {
            // Flat, jagged, sigma
            std::vector<std::string> keys = {"sharpf", "sharpj", "sharp"};

            for (size_t i = 0; i != params.size(); ++i) {
                query_map_.emplace(keys[i], params[i]);
            }
        }
    } else if (key == "mod") {  // type == typeid(std::vector<float>)
        auto params = tokenize<float>(value, ",", 3);

        // Brightness, saturation, hue
        std::vector<std::string> keys = {/*"bri"*/key, "sat", "hue"};

        for (size_t i = 0; i != params.size(); ++i) {
            /*keys[i] == "hue"*/ i == 2  // Hue needs to be cast to an integer
                ? query_map_.emplace(keys[i], static_cast<int>(params[i]))
                : query_map_.emplace(keys[i], params[i]);
        }
    } else if (key == "crop") {  // Deprecated
        auto coordinates = tokenize<int>(value, ",", 4);

        if (coordinates.size() == 4) {
            query_map_.emplace("cw", Coordinate{coordinates[0]});
            query_map_.emplace("ch", Coordinate{coordinates[1]});
            query_map_.emplace("cx", Coordinate{coordinates[2]});
            query_map_.emplace("cy", Coordinate{coordinates[3]});
        }
    }
}

Query::Query(const std::string &value) {
    size_t pos = 0;
    size_t max_pos = value.size();

    while (pos < max_pos) {
        // Search key
        size_t end = value.find_first_of("=&", pos);
        if (end == std::string::npos) {
            end = max_pos;
        }

        std::string key = value.substr(pos, end - pos);

        // Skip empty, invalid, or keys already handled in the nginx module
        if (key.empty() || key.size() > MAX_KEY_LENGTH ||
            nginx_keys.find(key) != nginx_keys.end()) {
            end = value.find('&', end);
            pos = end == std::string::npos ? max_pos : end + 1;
            continue;
        }

        // Handle synonyms
        auto synonym_it = synonym_map.find(key);
        if (synonym_it != synonym_map.end()) {
            key = synonym_it->second;
        }

        // Check whether the key is defined by the API
        auto type_it = type_map.find(key);
        if (type_it != type_map.end()) {
            // -1 by default
            std::string val = "-1";

            // Handle optional value
            if (end < max_pos && value.at(end) == '=') {
                pos = end + 1;
                end = value.find('&', pos);

                val = value.substr(pos, end - pos);
            }

            add_value(key, val, type_it->second);
        } else {
            end = value.find('&', end);
        }

        pos = end == std::string::npos ? max_pos : end + 1;
    }
}

}  // namespace weserv::api::parsers
