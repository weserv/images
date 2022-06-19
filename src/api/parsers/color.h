#pragma once

#include "base.h"

#include <string>
#include <unordered_map>
#include <vector>

namespace weserv::api::parsers {

class Color {
 public:
    /**
     * Pre-defined default color.
     */
    static const Color &DEFAULT;

    Color() = default;

    Color(int alpha, int red, int green, int blue)
        : alpha_(alpha), red_(red), green_(green), blue_(blue) {}

    /**
     * Indicates if this color is completely transparent.
     * @return A bool indicating if the color is transparent.
     */
    bool is_transparent() const;

    /**
     * Indicates if this color is opaque.
     * @return A bool indicating if the color is opaque.
     */
    bool is_opaque() const;

    /**
     * Color to RGBA vector.
     * @return The RGBA color as vector.
     */
    std::vector<double> to_rgba() const;

    /**
     * RGB to LAB, making the usual sRGB assumptions.
     * @return The LAB color as vector.
     */
    std::vector<double> to_lab() const;

    /**
     * Color to RGBA string.
     * @return The RGBA color as string representation.
     */
    std::string to_string() const;

 private:
    int alpha_{0};
    int red_{0};
    int green_{0};
    int blue_{0};
};

template <>
Color parse<Color>(const std::string &value);

/**
 * The 140 color names supported by all modern browsers.
 */
using ColorMap = std::unordered_map<std::string, std::string>;

}  // namespace weserv::api::parsers
