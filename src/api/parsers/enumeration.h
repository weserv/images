#pragma once

#include "base.h"
#include "enums.h"

namespace weserv {
namespace api {
namespace parsers {

using namespace enums;

template <>
inline Position parse(const std::string &value) {
    // Deprecated parameters
    if (value == "t") {
        return Position::Top;
    } else if (value == "l") {
        return Position::Left;
    } else if (value == "r") {
        return Position::Right;
    } else if (value == "b") {
        return Position::Bottom;
    } else if (value.rfind("crop-", 0) == 0) {
        return Position::Focal;
    }
    // End of deprecated parameters

    if (value == "top-left") {
        return Position::TopLeft;
    } else if (value == "top") {
        return Position::Top;
    } else if (value == "top-right") {
        return Position::TopRight;
    } else if (value == "left") {
        return Position::Left;
    } else if (value == "right") {
        return Position::Right;
    } else if (value == "bottom-left") {
        return Position::BottomLeft;
    } else if (value == "bottom") {
        return Position::Bottom;
    } else if (value == "bottom-right") {
        return Position::BottomRight;
    } else if (value.rfind("focal-", 0) == 0) {
        return Position::Focal;
    } else if (value == "entropy") {
        return Position::Entropy;
    } else if (value == "attention") {
        return Position::Attention;
    } else /*if (value == "center")*/ {
        // Center by default
        return Position::Center;
    }
}  // namespace parsers

template <>
inline FilterType parse(const std::string &value) {
    if (value == "greyscale") {
        return FilterType::Greyscale;
    } else if (value == "sepia") {
        return FilterType::Sepia;
    } else if (value == "duotone") {
        return FilterType::Duotone;
    } else if (value == "negate") {
        return FilterType::Negate;
    } else /*if (value == "none")*/ {
        return FilterType::None;
    }
}

template <>
inline MaskType parse(const std::string &value) {
    if (value == "circle") {
        return MaskType::Circle;
    } else if (value == "ellipse") {
        return MaskType::Ellipse;
    } else if (value == "triangle") {
        return MaskType::Triangle;
    } else if (value == "triangle-180") {
        return MaskType::Triangle180;
    } else if (value == "pentagon") {
        return MaskType::Pentagon;
    } else if (value == "pentagon-180") {
        return MaskType::Pentagon180;
    } else if (value == "hexagon") {
        return MaskType::Hexagon;
    } else if (value == "square") {
        return MaskType::Square;
    } else if (value == "star") {
        return MaskType::Star;
    } else if (value == "heart") {
        return MaskType::Heart;
    } else /*if (value == "none")*/ {
        return MaskType::None;
    }
}

template <>
inline Output parse(const std::string &value) {
    if (value == "jpg") {
        return Output::Jpeg;
    } else if (value == "png") {
        return Output::Png;
    } else if (value == "gif") {
        return Output::Gif;
    } else if (value == "tiff" || value == "tif") {
        return Output::Tiff;
    } else if (value == "webp") {
        return Output::Webp;
    } else if (value == "json") {
        return Output::Json;
    } else /*if (value == "origin")*/ {
        // Honor the origin image format by default
        return Output::Origin;
    }
}

template <>
inline Canvas parse(const std::string &value) {
    // Deprecated parameters
    if (value == "fit" || value == "fitup") {
        return Canvas::Max;
    } else if (value == "square" || value == "squaredown" ||
               value.rfind("crop", 0) == 0) {
        return Canvas::Crop;
    } else if (value == "absolute") {
        return Canvas::IgnoreAspect;
    } else if (value == "letterbox") {
        return Canvas::Embed;
    }
    // End of deprecated parameters

    if (value == "contain") {
        return Canvas::Embed;
    } else if (value == "cover") {
        return Canvas::Crop;
    } else if (value == "fill") {
        return Canvas::IgnoreAspect;
    } else if (value == "outside") {
        return Canvas::Min;
    } else /*if (value == "inside")*/ {
        // Inside (Canvas::Max) by default
        return Canvas::Max;
    }
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
