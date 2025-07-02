#pragma once

#include "base.h"

#include <variant>

namespace weserv::api::parsers {

class Coordinate {
 public:
    /**
     * Represents an invalid/uninitialized coordinate.
     */
    static const Coordinate &INVALID;

    Coordinate() = default;

    explicit Coordinate(std::variant<int, float> value) : value_(value) {}

    /**
     * Converts a coordinate into its pixel equivalent.
     * Percentage-based coordinates will be calculated relative to the provided
     * base value.
     * @param base The base value for calculating percentage-based coordinates.
     * @return The resolved coordinate in pixels.
     */
    int to_pixels(int base) const;

 private:
    std::variant<int, float> value_{-1};
};

template <>
Coordinate parse<Coordinate>(const std::string &value);

}  // namespace weserv::api::parsers
