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
    if (value.empty()) {
        return Coordinate::INVALID;
    }

    size_t percent_pos = std::string::npos;
    if (value.back() == '%') {
        percent_pos = value.size() - 1;
    } else if (value.size() > 3) {
        // Support URL-encoded percent signs as well
        percent_pos = value.find("%25", value.size() - 3);
    }

    if (percent_pos != std::string::npos) {
        try {
            float result = std::stof(value.substr(0, percent_pos));

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
