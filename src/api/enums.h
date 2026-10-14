#pragma once

#include <vips/vips8>

namespace weserv::api::enums {

enum class Position {
    Center = VIPS_INTERESTING_CENTRE,  // Default
    Entropy,
    Attention,
    Top,
    Right,
    Bottom,
    Left,
    TopLeft,
    BottomLeft,
    BottomRight,
    TopRight,
    Focal
};

enum class FilterType {
    None,  // Default
    Greyscale,
    Sepia,
    Duotone,
    Negate
};

enum class MaskType {
    None,  // Default
    Circle,
    Ellipse,
    Triangle,
    Triangle180,
    Pentagon,
    Pentagon180,
    Hexagon,
    Square,
    Star,
    Heart
};

enum class ImageType {
    Jpeg,
    Jxl,
    Png,
    Webp,
    Tiff,
    Gif,
    Svg,
    Pdf,
    Heif,
    Magick,
    Unknown
};

enum class Kernel {
    Auto = -1,  // Resolved from the input image, see Stream::resolve_query
    Nearest = VIPS_KERNEL_NEAREST,
    Linear = VIPS_KERNEL_LINEAR,
    Cubic = VIPS_KERNEL_CUBIC,
    Mitchell = VIPS_KERNEL_MITCHELL,
    Lanczos2 = VIPS_KERNEL_LANCZOS2,
    Lanczos3 = VIPS_KERNEL_LANCZOS3,  // Default
    Mks2013 = VIPS_KERNEL_MKS2013,
    Mks2021 = VIPS_KERNEL_MKS2021
};

enum class Canvas {
    Max,  // Default
    Min,
    Crop,
    Embed,
    IgnoreAspect
};

}  // namespace weserv::api::enums
