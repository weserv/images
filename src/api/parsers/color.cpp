#include "color.h"

#include <algorithm>
#include <cctype>
#include <cmath>
#include <iomanip>
#include <sstream>

namespace weserv::api::parsers {

// clang-format off
const ColorMap &color_map = {
        {"aliceblue",            "f0f8ff"},
        {"antiquewhite",         "faebd7"},
        {"aqua",                 "00ffff"},
        {"aquamarine",           "7fffd4"},
        {"azure",                "f0ffff"},
        {"beige",                "f5f5dc"},
        {"bisque",               "ffe4c4"},
        {"black",                "000000"},
        {"blanchedalmond",       "ffebcd"},
        {"blue",                 "0000ff"},
        {"blueviolet",           "8a2be2"},
        {"brown",                "a52a2a"},
        {"burlywood",            "deb887"},
        {"cadetblue",            "5f9ea0"},
        {"chartreuse",           "7fff00"},
        {"chocolate",            "d2691e"},
        {"coral",                "ff7f50"},
        {"cornflowerblue",       "6495ed"},
        {"cornsilk",             "fff8dc"},
        {"crimson",              "dc143c"},
        {"cyan",                 "00ffff"},
        {"darkblue",             "00008b"},
        {"darkcyan",             "008b8b"},
        {"darkgoldenrod",        "b8860b"},
        {"darkgray",             "a9a9a9"},
        {"darkgreen",            "006400"},
        {"darkkhaki",            "bdb76b"},
        {"darkmagenta",          "8b008b"},
        {"darkolivegreen",       "556b2f"},
        {"darkorange",           "ff8c00"},
        {"darkorchid",           "9932cc"},
        {"darkred",              "8b0000"},
        {"darksalmon",           "e9967a"},
        {"darkseagreen",         "8fbc8f"},
        {"darkslateblue",        "483d8b"},
        {"darkslategray",        "2f4f4f"},
        {"darkturquoise",        "00ced1"},
        {"darkviolet",           "9400d3"},
        {"deeppink",             "ff1493"},
        {"deepskyblue",          "00bfff"},
        {"dimgray",              "696969"},
        {"dodgerblue",           "1e90ff"},
        {"firebrick",            "b22222"},
        {"floralwhite",          "fffaf0"},
        {"forestgreen",          "228b22"},
        {"fuchsia",              "ff00ff"},
        {"gainsboro",            "dcdcdc"},
        {"ghostwhite",           "f8f8ff"},
        {"gold",                 "ffd700"},
        {"goldenrod",            "daa520"},
        {"gray",                 "808080"},
        {"green",                "008000"},
        {"greenyellow",          "adff2f"},
        {"honeydew",             "f0fff0"},
        {"hotpink",              "ff69b4"},
        {"indianred",            "cd5c5c"},
        {"indigo",               "4b0082"},
        {"ivory",                "fffff0"},
        {"khaki",                "f0e68c"},
        {"lavender",             "e6e6fa"},
        {"lavenderblush",        "fff0f5"},
        {"lawngreen",            "7cfc00"},
        {"lemonchiffon",         "fffacd"},
        {"lightblue",            "add8e6"},
        {"lightcoral",           "f08080"},
        {"lightcyan",            "e0ffff"},
        {"lightgoldenrodyellow", "fafad2"},
        {"lightgray",            "d3d3d3"},
        {"lightgreen",           "90ee90"},
        {"lightpink",            "ffb6c1"},
        {"lightsalmon",          "ffa07a"},
        {"lightseagreen",        "20b2aa"},
        {"lightskyblue",         "87cefa"},
        {"lightslategray",       "778899"},
        {"lightsteelblue",       "b0c4de"},
        {"lightyellow",          "ffffe0"},
        {"lime",                 "00ff00"},
        {"limegreen",            "32cd32"},
        {"linen",                "faf0e6"},
        {"magenta",              "ff00ff"},
        {"maroon",               "800000"},
        {"mediumaquamarine",     "66cdaa"},
        {"mediumblue",           "0000cd"},
        {"mediumorchid",         "ba55d3"},
        {"mediumpurple",         "9370db"},
        {"mediumseagreen",       "3cb371"},
        {"mediumslateblue",      "7b68ee"},
        {"mediumspringgreen",    "00fa9a"},
        {"mediumturquoise",      "48d1cc"},
        {"mediumvioletred",      "c71585"},
        {"midnightblue",         "191970"},
        {"mintcream",            "f5fffa"},
        {"mistyrose",            "ffe4e1"},
        {"moccasin",             "ffe4b5"},
        {"navajowhite",          "ffdead"},
        {"navy",                 "000080"},
        {"oldlace",              "fdf5e6"},
        {"olive",                "808000"},
        {"olivedrab",            "6b8e23"},
        {"orange",               "ffa500"},
        {"orangered",            "ff4500"},
        {"orchid",               "da70d6"},
        {"palegoldenrod",        "eee8aa"},
        {"palegreen",            "98fb98"},
        {"paleturquoise",        "afeeee"},
        {"palevioletred",        "db7093"},
        {"papayawhip",           "ffefd5"},
        {"peachpuff",            "ffdab9"},
        {"peru",                 "cd853f"},
        {"pink",                 "ffc0cb"},
        {"plum",                 "dda0dd"},
        {"powderblue",           "b0e0e6"},
        {"purple",               "800080"},
        {"rebeccapurple",        "663399"},
        {"red",                  "ff0000"},
        {"rosybrown",            "bc8f8f"},
        {"royalblue",            "4169e1"},
        {"saddlebrown",          "8b4513"},
        {"salmon",               "fa8072"},
        {"sandybrown",           "f4a460"},
        {"seagreen",             "2e8b57"},
        {"seashell",             "fff5ee"},
        {"sienna",               "a0522d"},
        {"silver",               "c0c0c0"},
        {"skyblue",              "87ceeb"},
        {"slateblue",            "6a5acd"},
        {"slategray",            "708090"},
        {"snow",                 "fffafa"},
        {"springgreen",          "00ff7f"},
        {"steelblue",            "4682b4"},
        {"tan",                  "d2b48c"},
        {"teal",                 "008080"},
        {"thistle",              "d8bfd8"},
        {"tomato",               "ff6347"},
        {"turquoise",            "40e0d0"},
        {"violet",               "ee82ee"},
        {"wheat",                "f5deb3"},
        {"white",                "ffffff"},
        {"whitesmoke",           "f5f5f5"},
        {"yellow",               "ffff00"},
        {"yellowgreen",          "9acd32"}
};
// clang-format on

const Color &Color::DEFAULT = Color();

bool Color::is_transparent() const {
    return alpha_ == 0;
}

bool Color::is_opaque() const {
    return alpha_ == 255;
}

std::vector<double> Color::to_rgba() const {
    return {static_cast<double>(red_), static_cast<double>(green_),
            static_cast<double>(blue_), static_cast<double>(alpha_)};
}

std::vector<double> Color::to_lab() const {
    double r = red_ / 255.0;
    double g = green_ / 255.0;
    double b = blue_ / 255.0;

    // Assume sRGB
    r = r > 0.04045 ? std::pow((r + 0.055) / 1.055, 2.4) : r / 12.92;
    g = g > 0.04045 ? std::pow((g + 0.055) / 1.055, 2.4) : g / 12.92;
    b = b > 0.04045 ? std::pow((b + 0.055) / 1.055, 2.4) : b / 12.92;

    double x = (r * 0.4124564 + g * 0.3575761 + b * 0.1804375) / 0.95047;
    double y = (r * 0.2126729 + g * 0.7151522 + b * 0.0721750) / 1.00000;
    double z = (r * 0.0193339 + g * 0.1191920 + b * 0.9503041) / 1.08883;

    x = (x > 0.008856) ? std::cbrt(x) : (7.787 * x + 16.0 / 116.0);
    y = (y > 0.008856) ? std::cbrt(y) : (7.787 * y + 16.0 / 116.0);
    z = (z > 0.008856) ? std::cbrt(z) : (7.787 * z + 16.0 / 116.0);

    return {
        (116.0 * y) - 16,  // L
        500 * (x - y),     // A
        200 * (y - z)      // B
    };
}

std::string Color::to_string() const {
    std::ostringstream ss;
    ss << "rgba(" << red_ << "," << green_ << "," << blue_ << "," << std::fixed
       << std::setprecision(2)
       << alpha_ * 0.00392156862  // 0.00392156862 == (1/255)
       << ")";
    return ss.str();
}

template <>
Color parse(const std::string &value) {
    // Default to transparent
    auto def_color = Color(0, 0, 0, 0);

    // Invalid color; return default
    if (value.empty()) {
        return def_color;
    }

    // Remove any leading hash
    auto color = value.rfind("%23", 0) == 0 ? value.substr(3) : value;

    // Make sure that the string is lowercased
    std::transform(color.begin(), color.end(), color.begin(), &tolower);

    auto it = color_map.find(color);

    if (it == color_map.end()) {
        auto check_xdigit = [](char ch) {
            return std::isxdigit(static_cast<unsigned char>(ch));
        };

        // Check if the strings contains only hexadecimal digits
        if (!std::all_of(color.begin(), color.end(), check_xdigit)) {
            return def_color;
        }
    } else {
        color = it->second;
    }

    // Get string length
    auto color_length = color.size();

    // Invalid color; return default
    if (color_length < 3 || color_length == 5 || color_length > 8) {
        return def_color;
    }

    // RGB -> RRGGBB
    if (color_length == 3) {
        char r = color.at(0);
        char g = color.at(1);
        char b = color.at(2);

        color = r;
        color += r;
        color += g;
        color += g;
        color += b;
        color += b;
    }

    // ARGB -> AARRGGBB
    if (color_length == 4) {
        char a = color.at(0);
        char r = color.at(1);
        char g = color.at(2);
        char b = color.at(3);

        color = a;
        color += a;
        color += r;
        color += r;
        color += g;
        color += g;
        color += b;
        color += b;
    }

    // Pad the string to AARRGGBB format
    if (color.size() < 8) {
        color.insert(color.begin(), 8 - color.size(), 'f');
    }

    // Color is now AARRGGBB; resolve the decimal ARGB sequence
    int a = std::stoi(color.substr(0, 2), nullptr, 16);
    int r = std::stoi(color.substr(2, 2), nullptr, 16);
    int g = std::stoi(color.substr(4, 2), nullptr, 16);
    int b = std::stoi(color.substr(6, 2), nullptr, 16);

    return {a, r, g, b};
}

}  // namespace weserv::api::parsers
