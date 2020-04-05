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

// Note: We check the `MAX_VALUE_LENGTH` within `numeric.h`

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
        {"sat",     typeid(float)},
};

const SynonymMap &synonym_map = {
        {"shape",   "mask"},   // &shape= was deprecated since API version 4
        {"strim",   "mtrim"},  // &strim= was deprecated since API version 4
        {"or",      "ro"},     // &or= was deprecated since API version 5
        {"t",       "fit"},    // &t= was deprecated since API version 5
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
std::vector<T> tokenize(const std::string &data, const std::string &delimiters,
                        const typename std::vector<T>::size_type max_items) {
    // Skip delimiters at beginning
    size_t last_pos = data.find_first_not_of(delimiters, 0);

    // Find first non-delimiter
    size_t pos = data.find_first_of(delimiters, last_pos);

    std::vector<T> vector;
    vector.reserve(max_items);

    int i = 0;
    while (std::string::npos != pos || std::string::npos != last_pos) {
        try {
            // Found a token, add it to the vector
            vector.push_back(parse<T>(data.substr(last_pos, pos - last_pos)));
        } catch (...) {
            // -1 by default
            vector.push_back(static_cast<T>(-1));
        }

        if (i++ >= max_items) {
            break;
        }

        // Skip delimiters
        last_pos = data.find_first_not_of(delimiters, pos);

        // Find next non-delimiter
        pos = data.find_first_of(delimiters, last_pos);
    }

    return vector;
}

void add_value(QueryMap &map, const std::string &key, const std::string &value,
               std::type_index type) {
    if (type == typeid(bool)) {
        // Only emplace `false` if it's explicitly specified because we
        // interpret empty strings (for e.g. `&we`) as `true`.
        map.emplace(key, value != "false" && value != "0");
    } else if (type == typeid(int)) {
        try {
            map.emplace(key, parse<int>(value));
        } catch (...) {
            // -1 by default
            map.emplace(key, -1);
        }
    } else if (type == typeid(float)) {
        try {
            map.emplace(key, parse<float>(value));
        } catch (...) {
            // -1.0 by default
            map.emplace(key, -1.0F);
        }
    } else if (type == typeid(std::vector<int>)) {
        if (key == "crop") {  // Deprecated
            auto coordinates = tokenize<int>(value, ",", 4);

            if (coordinates.size() == 4) {
                map.emplace("cw", coordinates[0]);
                map.emplace("ch", coordinates[1]);
                map.emplace("cx", coordinates[2]);
                map.emplace("cy", coordinates[3]);
            }
        } else {  // key == "delay"
#if VIPS_VERSION_AT_LEAST(8, 9, 0)
            // 256 is the maximum number of pages we're trying to load
            // (should be plenty)
            auto delays = tokenize<int>(value, ",", 256);
#else
            // Limit to 1 value if multiple delay values are not supported
            auto delays = tokenize<int>(value, ",", 1);
#endif
            map.emplace(key, delays);
        }
    } else if (type == typeid(std::vector<float>)) {
        auto params = tokenize<float>(value, ",", 3);

        if (params.size() == 1) {
            map.emplace("sharp", params[0]);
        } else {
            // Flat, jagged, sigma
            std::vector<std::string> keys = {"sharpf", "sharpj", "sharp"};

            for (size_t i = 0; i != params.size(); ++i) {
                // A single piece needs to be in the range of 0 - 10000
                if (params[i] >= 0 && params[i] <= 10000) {
                    map.emplace(keys[i], params[i]);
                }
            }
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

            map.emplace("focal_x", focal[0]);
            map.emplace("focal_y", focal[1]);
        }

        map.emplace(key, utils::underlying_value(position));
    } else if (type == typeid(FilterType)) {
        map.emplace(key, utils::underlying_value(parse<FilterType>(value)));
    } else if (type == typeid(MaskType)) {
        map.emplace(key, utils::underlying_value(parse<MaskType>(value)));
    } else if (type == typeid(Output)) {
        map.emplace(key, utils::underlying_value(parse<Output>(value)));
    } else if (type == typeid(Canvas)) {
        // Deprecated without enlargement parameters
        if (value == "fit" || value == "squaredown") {
            map.emplace("we", true);
        }

        map.emplace(key, utils::underlying_value(parse<Canvas>(value)));
    } else if (type == typeid(Color)) {
        map.emplace(key, parse<Color>(value));
    }
}

template <>
QueryHolderPtr parse(const std::string &value) {
    QueryMap m;

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

            add_value(m, key, val, type_it->second);
        }

        if (key_pos != std::string::npos) {
            ++key_pos;
        }
    }

    return std::make_shared<QueryHolder>(m);
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
