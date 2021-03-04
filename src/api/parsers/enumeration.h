#pragma once

#include "enums.h"
#include "parsers/base.h"

#include <weserv/enums.h>

namespace weserv {
namespace api {
namespace parsers {

template <>
inline enums::Position parse(const std::string &value) {
    // Deprecated parameters
    if (value == "t") {
        return enums::Position::Top;
    } else if (value == "l") {
        return enums::Position::Left;
    } else if (value == "r") {
        return enums::Position::Right;
    } else if (value == "b") {
        return enums::Position::Bottom;
    } else if (value.rfind("crop-", 0) == 0) {
        return enums::Position::Focal;
    }
    // End of deprecated parameters

    if (value == "top-left") {
        return enums::Position::TopLeft;
    } else if (value == "top") {
        return enums::Position::Top;
    } else if (value == "top-right") {
        return enums::Position::TopRight;
    } else if (value == "left") {
        return enums::Position::Left;
    } else if (value == "right") {
        return enums::Position::Right;
    } else if (value == "bottom-left") {
        return enums::Position::BottomLeft;
    } else if (value == "bottom") {
        return enums::Position::Bottom;
    } else if (value == "bottom-right") {
        return enums::Position::BottomRight;
    } else if (value.rfind("focal", 0) == 0) {
        return enums::Position::Focal;
    } else if (value == "entropy") {
        return enums::Position::Entropy;
    } else if (value == "attention") {
        return enums::Position::Attention;
    } else /*if (value == "center")*/ {
        // Center by default
        return enums::Position::Center;
    }
}  // namespace parsers

template <>
inline enums::FilterType parse(const std::string &value) {
    if (value == "greyscale") {
        return enums::FilterType::Greyscale;
    } else if (value == "sepia") {
        return enums::FilterType::Sepia;
    } else if (value == "duotone") {
        return enums::FilterType::Duotone;
    } else if (value == "negate") {
        return enums::FilterType::Negate;
    } else /*if (value == "none")*/ {
        return enums::FilterType::None;
    }
}

template <>
inline enums::MaskType parse(const std::string &value) {
    if (value == "circle") {
        return enums::MaskType::Circle;
    } else if (value == "ellipse") {
        return enums::MaskType::Ellipse;
    } else if (value == "triangle") {
        return enums::MaskType::Triangle;
    } else if (value == "triangle-180") {
        return enums::MaskType::Triangle180;
    } else if (value == "pentagon") {
        return enums::MaskType::Pentagon;
    } else if (value == "pentagon-180") {
        return enums::MaskType::Pentagon180;
    } else if (value == "hexagon") {
        return enums::MaskType::Hexagon;
    } else if (value == "square") {
        return enums::MaskType::Square;
    } else if (value == "star") {
        return enums::MaskType::Star;
    } else if (value == "heart") {
        return enums::MaskType::Heart;
    } else /*if (value == "none")*/ {
        return enums::MaskType::None;
    }
}

template <>
inline enums::Output parse(const std::string &value) {
    if (value == "jpg") {
        return enums::Output::Jpeg;
    } else if (value == "png") {
        return enums::Output::Png;
    } else if (value == "gif") {
        return enums::Output::Gif;
    } else if (value == "tiff" || value == "tif") {
        return enums::Output::Tiff;
    } else if (value == "webp") {
        return enums::Output::Webp;
    } else if (value == "avif" || value == "av1") {
        return enums::Output::Avif;
    } else if (value == "json") {
        return enums::Output::Json;
    } else /*if (value == "origin")*/ {
        // Honor the origin image format by default
        return enums::Output::Origin;
    }
}

template <>
inline enums::Canvas parse(const std::string &value) {
    // Deprecated parameters
    if (value == "fit" || value == "fitup") {
        return enums::Canvas::Max;
    } else if (value == "square" || value == "squaredown" ||
               value.rfind("crop", 0) == 0) {
        return enums::Canvas::Crop;
    } else if (value == "absolute") {
        return enums::Canvas::IgnoreAspect;
    } else if (value == "letterbox") {
        return enums::Canvas::Embed;
    }
    // End of deprecated parameters

    if (value == "contain") {
        return enums::Canvas::Embed;
    } else if (value == "cover") {
        return enums::Canvas::Crop;
    } else if (value == "fill") {
        return enums::Canvas::IgnoreAspect;
    } else if (value == "outside") {
        return enums::Canvas::Min;
    } else /*if (value == "inside")*/ {
        // Inside (enums::Canvas::Max) by default
        return enums::Canvas::Max;
    }
}

}  // namespace parsers
}  // namespace api
}  // namespace weserv
