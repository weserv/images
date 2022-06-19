#pragma once

#include <cstdint>

namespace weserv::api::enums {

enum class Output : uintptr_t {
    Origin = 1U << 0,  // Default
    Jpeg = 1U << 1,
    Png = 1U << 2,
    Webp = 1U << 3,
    Avif = 1U << 4,
    Tiff = 1U << 5,
    Gif = 1U << 6,
    Json = 1U << 7,
    All = Jpeg | Png | Webp | Avif | Tiff | Gif | Json,  // 0xFE
};

inline constexpr Output operator&(Output x, Output y) {
    return static_cast<Output>(static_cast<uintptr_t>(x) &
                               static_cast<uintptr_t>(y));
}

inline constexpr Output operator|(Output x, Output y) {
    return static_cast<Output>(static_cast<uintptr_t>(x) |
                               static_cast<uintptr_t>(y));
}

inline constexpr Output operator^(Output x, Output y) {
    return static_cast<Output>(static_cast<uintptr_t>(x) ^
                               static_cast<uintptr_t>(y));
}

inline constexpr Output operator~(Output x) {
    return static_cast<Output>(~static_cast<uintptr_t>(x));
}

inline Output &operator&=(Output &x, Output y) {
    x = x & y;
    return x;
}

inline Output &operator|=(Output &x, Output y) {
    x = x | y;
    return x;
}

inline Output &operator^=(Output &x, Output y) {
    x = x ^ y;
    return x;
}

}  // namespace weserv::api::enums
