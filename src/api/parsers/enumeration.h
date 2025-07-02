#pragma once

#include "../enums.h"
#include "base.h"

#include <weserv/enums.h>

namespace weserv::api::parsers {

template <>
inline enums::Position parse(const std::string &value) {
    // Deprecated parameters
    if (value == "t") {
        return enums::Position::Top;
    }
    if (value == "l") {
        return enums::Position::Left;
    }
    if (value == "r") {
        return enums::Position::Right;
    }
    if (value == "b") {
        return enums::Position::Bottom;
    }
    if (value.rfind("crop-", 0) == 0) {
        return enums::Position::Focal;
    }
    // End of deprecated parameters

    if (value == "top-left") {
        return enums::Position::TopLeft;
    }
    if (value == "top") {
        return enums::Position::Top;
    }
    if (value == "top-right") {
        return enums::Position::TopRight;
    }
    if (value == "left") {
        return enums::Position::Left;
    }
    if (value == "right") {
        return enums::Position::Right;
    }
    if (value == "bottom-left") {
        return enums::Position::BottomLeft;
    }
    if (value == "bottom") {
        return enums::Position::Bottom;
    }
    if (value == "bottom-right") {
        return enums::Position::BottomRight;
    }
    if (value.rfind("focal", 0) == 0) {
        return enums::Position::Focal;
    }
    if (value == "entropy") {
        return enums::Position::Entropy;
    }
    if (value == "attention") {
        return enums::Position::Attention;
    }
    // if (value == "center")

    // Center by default
    return enums::Position::Center;
}

template <>
inline enums::FilterType parse(const std::string &value) {
    if (value == "greyscale") {
        return enums::FilterType::Greyscale;
    }
    if (value == "sepia") {
        return enums::FilterType::Sepia;
    }
    if (value == "duotone") {
        return enums::FilterType::Duotone;
    }
    if (value == "negate") {
        return enums::FilterType::Negate;
    }
    // if (value == "none")

    return enums::FilterType::None;
}

template <>
inline enums::MaskType parse(const std::string &value) {
    if (value == "circle") {
        return enums::MaskType::Circle;
    }
    if (value == "ellipse") {
        return enums::MaskType::Ellipse;
    }
    if (value == "triangle") {
        return enums::MaskType::Triangle;
    }
    if (value == "triangle-180") {
        return enums::MaskType::Triangle180;
    }
    if (value == "pentagon") {
        return enums::MaskType::Pentagon;
    }
    if (value == "pentagon-180") {
        return enums::MaskType::Pentagon180;
    }
    if (value == "hexagon") {
        return enums::MaskType::Hexagon;
    }
    if (value == "square") {
        return enums::MaskType::Square;
    }
    if (value == "star") {
        return enums::MaskType::Star;
    }
    if (value == "heart") {
        return enums::MaskType::Heart;
    }
    // if (value == "none")

    return enums::MaskType::None;
}

template <>
inline enums::Output parse(const std::string &value) {
    if (value == "jpeg" || value == "jpg") {
        return enums::Output::Jpeg;
    }
    if (value == "png") {
        return enums::Output::Png;
    }
    if (value == "gif") {
        return enums::Output::Gif;
    }
    if (value == "tiff" || value == "tif") {
        return enums::Output::Tiff;
    }
    if (value == "webp") {
        return enums::Output::Webp;
    }
    if (value == "avif" || value == "av1") {
        return enums::Output::Avif;
    }
    if (value == "json") {
        return enums::Output::Json;
    }
    // if (value == "origin")

    // Honor the origin image format by default
    return enums::Output::Origin;
}

template <>
inline enums::Canvas parse(const std::string &value) {
    // Deprecated parameters
    if (value == "fit" || value == "fitup") {
        return enums::Canvas::Max;
    }
    if (value == "square" || value == "squaredown" ||
        value.rfind("crop", 0) == 0) {
        return enums::Canvas::Crop;
    }
    if (value == "absolute") {
        return enums::Canvas::IgnoreAspect;
    }
    if (value == "letterbox") {
        return enums::Canvas::Embed;
    }
    // End of deprecated parameters

    if (value == "contain") {
        return enums::Canvas::Embed;
    }
    if (value == "cover") {
        return enums::Canvas::Crop;
    }
    if (value == "fill") {
        return enums::Canvas::IgnoreAspect;
    }
    if (value == "outside") {
        return enums::Canvas::Min;
    }
    // if (value == "inside")

    // Inside (enums::Canvas::Max) by default
    return enums::Canvas::Max;
}

}  // namespace weserv::api::parsers
