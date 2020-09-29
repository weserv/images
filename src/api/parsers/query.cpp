#include "parsers/query.h"

namespace weserv {
namespace api {
namespace parsers {

using enums::Canvas;
using enums::FilterType;
using enums::MaskType;
using enums::Output;
using enums::Position;

// `&[precrop]=true`
constexpr size_t MAX_KEY_LENGTH = sizeof("precrop") - 1;

// A vector must not have more than 65536 elements.
const size_t MAX_VECTOR_SIZE = 65536;

// Note: We check crazy numbers within `numeric.h`

// clang-format off
const TypeMap &type_map = {
        {"w",       typeid(int)},
        {"h",       typeid(int)},
        {"dpr",     typeid(float)},
        {"fit",     typeid(Canvas)},
        {"we",      typeid(bool)},
        {"crop",    typeid(std::vector<int>)},  // Deprecated
        {"cx",      typeid(int)},
        {"cy",      typeid(int)},
        {"cw",      typeid(int)},
        {"ch",      typeid(int)},
        {"precrop", typeid(bool)},
        {"a",       typeid(Position)},
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
        {"af",      typeid(bool)},
        {"page",    typeid(int)},
        {"n",       typeid(int)},
        {"loop",    typeid(int)},               // TODO(kleisauke): Documentation needed.
        {"delay",   typeid(std::vector<int>)},  // TODO(kleisauke): Documentation needed.
        {"fsol",    typeid(bool)},              // TODO(kleisauke): Documentation needed.
};

const SynonymMap &synonym_map = {
        {"shape",   "mask"},   // &shape= was deprecated since API version 4
        {"strim",   "mtrim"},  // &strim= was deprecated since API version 4
        {"or",      "ro"},     // &or= was deprecated since API version 5
        {"t",       "fit"},    // &t= was deprecated since API version 5
        // TODO(kleisauke): Synonym this within a major release (since it breaks backwards compatibility).
        //{"bri",     "mod"},
        // Some handy synonyms
        {"pages",   "n"},
        {"width",   "w"},
        {"height",  "h"},
        {"align",   "a"},
        {"level",   "l"},
        {"quality", "q"},
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
    while (std::string::npos != pos || std::string::npos != last_pos) {
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
        // interpret empty strings (for e.g. `&we`) as `true`.
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
    } else if (type == typeid(Position)) {
        auto position = parse<Position>(value);
        if (position == Position::Focal) {
            // Center on default
            std::vector<int> focal = {50, 50};

            auto values = value.substr(value.find_first_of('-') + 1);
            auto params = tokenize<int>(values, "-", 2);

            for (size_t i = 0; i != params.size(); ++i) {
                // A single percentage needs to be in the range of 0 - 100
                if (params[i] >= 0 && params[i] <= 100) {
                    focal[i] = params[i];
                }
            }

            query_map_.emplace("focal_x", focal[0]);
            query_map_.emplace("focal_y", focal[1]);
        }

        query_map_.emplace(key, utils::underlying_value(position));
    } else if (type == typeid(FilterType)) {
        query_map_.emplace(key,
                           utils::underlying_value(parse<FilterType>(value)));
    } else if (type == typeid(MaskType)) {
        query_map_.emplace(key,
                           utils::underlying_value(parse<MaskType>(value)));
    } else if (type == typeid(Output)) {
        query_map_.emplace(key, utils::underlying_value(parse<Output>(value)));
    } else if (type == typeid(Canvas)) {
        // Deprecated without enlargement parameters
        if (value == "fit" || value == "squaredown") {
            query_map_.emplace("we", true);
        }

        query_map_.emplace(key, utils::underlying_value(parse<Canvas>(value)));
    } else if (type == typeid(Color)) {
        query_map_.emplace(key, parse<Color>(value));
    } else if (key == "delay") {  // type == typeid(std::vector<int>)
#if VIPS_VERSION_AT_LEAST(8, 9, 0)
        // Multiple delay values are supported, limit to config_.max_pages
        auto delays = tokenize<int>(value, ",",
                                    config_.max_pages > 0
                                        ? static_cast<size_t>(config_.max_pages)
                                        : MAX_VECTOR_SIZE);
#else
        // Limit to 1 value if multiple delay values are not supported
        auto delays = tokenize<int>(value, ",", 1);
#endif
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
            /*keys[i] == "hue"*/ i == 2  // Hue needs to be casted to a integer
                ? query_map_.emplace(keys[i], static_cast<int>(params[i]))
                : query_map_.emplace(keys[i], params[i]);
        }
    } else if (key == "crop") {  // Deprecated
        auto coordinates = tokenize<int>(value, ",", 4);

        if (coordinates.size() == 4) {
            query_map_.emplace("cw", coordinates[0]);
            query_map_.emplace("ch", coordinates[1]);
            query_map_.emplace("cx", coordinates[2]);
            query_map_.emplace("cy", coordinates[3]);
        }
    }
}

Query::Query(const std::string &value, const Config &config) : config_(config) {
    size_t key_pos = 0;
    size_t key_end;
    size_t val_pos;
    size_t val_end;

    size_t max_pos = value.size();

    while (key_pos < max_pos) {
        key_end = value.find_first_of("=&", key_pos);
        if (key_end == std::string::npos) {
            key_end = max_pos;
        }

        std::string key = value.substr(key_pos, key_end - key_pos);

        if (key.empty() || key.size() > MAX_KEY_LENGTH ||
            // Handled in the nginx module
            key == "url" || key == "default" || key == "errorredirect" ||
            key == "filename" || key == "encoding" || key == "maxage") {
            key_pos = key_end + 1;
            continue;
        }

        auto synonym_it = synonym_map.find(key);
        if (synonym_it != synonym_map.end()) {
            key = synonym_it->second;
        }

        auto type_it = type_map.find(key);
        if (type_it != type_map.end()) {
            std::string val;
            if (key_end < max_pos && value.at(key_end) == '=') {
                val_pos = key_end + 1;
                val_end = value.find('&', val_pos);

                val = value.substr(val_pos, val_end - val_pos);

                key_pos = val_end;
            } else {
                val = "-1";
                key_pos = key_end;
            }

            add_value(key, val, type_it->second);
        }

        if (key_pos != std::string::npos) {
            ++key_pos;
        }
    }
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
