#include "coordinate.h"

#include "numeric.h"

#include <cmath>

namespace weserv::api::parsers {

const Coordinate &Coordinate::INVALID = Coordinate(-1);

int Coordinate::to_pixels(int base) const {
    if (const auto *relative_coord = std::get_if<float>(&value_)) {
        return static_cast<int>(std::round(*relative_coord * base));
    }

    return std::get<int>(value_);
}

template <>
Coordinate parse(const std::string &value) {
    if (value.back() == '%') {
        try {
            float result = std::stof(value.substr(0, value.size() - 1));

            // A single percentage needs to be in the range of 0 - 100
            if (result < 0.0 || result > 100.0) {
                throw std::out_of_range(
                    "parse<Coordinate>(): percentage is out of range");
            }
            return Coordinate{result / 100.0F};
        } catch (...) {
            return Coordinate::INVALID;
        }
    }

    try {
        return Coordinate{parse<int>(value)};
    } catch (...) {
        return Coordinate::INVALID;
    }
}
}  // namespace weserv::api::parsers
