#include "mask.h"

#include "../io/blob.h"
#include "../utils/utility.h"

#include <algorithm>
#include <cmath>
#include <iomanip>
#include <limits>
#include <ostream>
#include <stdexcept>
#include <tuple>

namespace weserv::api::processors {

using enums::MaskType;
using parsers::Color;

using io::Blob;

std::string Mask::svg_path_by_type(const int width, const int height,
                                   const MaskType &mask,
                                   int *out_x_min, int *out_y_min,
                                   int *out_width, int *out_height) const {
    int min = std::min(width, height);
    float outer_radius = static_cast<float>(min) / 2.0;
    float mid_x = static_cast<float>(width) / 2.0;
    float mid_y = static_cast<float>(height) / 2.0;

    if (mask == MaskType::Heart) {
        std::vector<PathCoordinate> coordinates =
            heart_path(outer_radius, outer_radius, out_x_min, out_y_min,
                       out_width, out_height);

        PathCoordinate mask_transl{};
        double scale;
        std::tie(mask_transl, scale) = translation_and_scaling(
            width, height, *out_x_min, *out_y_min, out_width, out_height);

        return transformed_path_string(coordinates, mask_transl, scale);
    }

    if (mask == MaskType::Ellipse) {
        *out_x_min = 0;
        *out_y_min = 0;
        *out_width = width;
        *out_height = height;

        return svg_ellipse_path(mid_x, mid_y, mid_x, mid_y);
    }

    if (mask == MaskType::Circle) {
        *out_x_min = static_cast<int>(std::round(mid_x - outer_radius));
        *out_y_min = static_cast<int>(std::round(mid_y - outer_radius));
        *out_width = min;
        *out_height = min;

        return svg_circle_path(mid_x, mid_y, outer_radius);
    }

    // 'inner' radius of the polygon/star
    float inner_radius = outer_radius;

    // Initial angle (clockwise). By default, stars and polygons are 'pointing'
    // up.
    double initial_angle = 0.0;

    // Number of points (or number of sides for polygons)
    int points = 0;

    switch (mask) {
        case MaskType::Hexagon:
            // Hexagon
            points = 6;
            break;
        case MaskType::Pentagon:
            // Pentagon
            points = 5;
            break;
        case MaskType::Pentagon180:
            // Pentagon tilted upside down
            points = 5;
            initial_angle = M_PI;
            break;
        case MaskType::Star:
            // 5 point star
            points = 5 * 2;
            inner_radius *= 0.382F;
            break;
        case MaskType::Square:
            // Square tilted 45 degrees
            points = 4;
            break;
        case MaskType::Triangle:
            // Triangle
            points = 3;
            break;
        case MaskType::Triangle180:
            // Triangle upside down
            points = 3;
            initial_angle = M_PI;
            break;
        default:  // LCOV_EXCL_START
            throw std::logic_error("Reached a supposed unreachable point");
            // LCOV_EXCL_STOP
    }

    std::vector<PathCoordinate> coordinates;

    float x_max = std::numeric_limits<float>::min();
    float y_max = std::numeric_limits<float>::min();
    float x_min = std::numeric_limits<float>::max();
    float y_min = std::numeric_limits<float>::max();

    for (int i = 0; i < points; ++i) {
        double angle = i * 2 * M_PI / points - M_PI / 2 + initial_angle;
        float radius = inner_radius;
        if (i % 2 == 0) {
            radius = outer_radius;
        }

        auto x = static_cast<float>(mid_x + radius * std::cos(angle));
        auto y = static_cast<float>(mid_y + radius * std::sin(angle));

        coordinates.push_back({x, y});

        x_max = std::max(x, x_max);
        y_max = std::max(y, y_max);
        x_min = std::min(x, x_min);
        y_min = std::min(y, y_min);
    }

    *out_x_min = static_cast<int>(std::round(x_min));
    *out_y_min = static_cast<int>(std::round(y_min));
    *out_width = static_cast<int>(std::round(x_max - x_min));
    *out_height = static_cast<int>(std::round(y_max - y_min));

    PathCoordinate mask_transl{};
    double scale;
    std::tie(mask_transl, scale) = translation_and_scaling(
        width, height, *out_x_min, *out_y_min, out_width, out_height);

    std::string path = transformed_path_string(coordinates, mask_transl, scale);

    // If an odd number of points, add a point at the top of the polygon; this
    // will shift the calculated center point of the shape so that the center
    // point of the polygon is at x,y (otherwise the center is mis-located)
    if (points % 2 == 1) {
        path = "M0 " + std::to_string(outer_radius) + " " + path;
    }

    return path;
}

std::string Mask::svg_circle_path(const float cx, const float cy,
                                  const float r) const {
    std::ostringstream ss;
    ss << "M " << cx - r << ", " << cy << "a" << r << "," << r << " 0 1,0 "
       << r * 2 << ",0 a " << r << "," << r << " 0 1,0 -" << r * 2 << ",0";
    return ss.str();
}

std::string Mask::svg_ellipse_path(const float cx, const float cy,
                                   const float rx, const float ry) const {
    std::ostringstream ss;
    ss << "M " << cx - rx << ", " << cy << "a" << rx << "," << ry << " 0 1,0 "
       << rx * 2 << ",0a" << rx << "," << ry << " 0 1,0 -" << rx * 2 << ",0";
    return ss.str();
}

std::vector<Mask::PathCoordinate>
Mask::heart_path(const float cx, const float cy, int *out_x_min, int *out_y_min,
                 int *out_width, int *out_height) const {
    std::vector<PathCoordinate> coordinates;

    float x_max = std::numeric_limits<float>::min();
    float y_max = std::numeric_limits<float>::min();
    float x_min = std::numeric_limits<float>::max();
    float y_min = std::numeric_limits<float>::max();

    for (size_t i = 0; i <= 314; ++i) {
        double t = -M_PI + (i * 0.02);
        double x_pt = 16.0 * (std::pow(std::sin(t), 3.0));
        double y_pt = 13.0 * std::cos(t) - 5 * std::cos(2 * t) -
                      2 * std::cos(3 * t) - std::cos(4 * t);

        auto x = static_cast<float>(cx + x_pt * cx);
        auto y = static_cast<float>(cy - y_pt * cy);

        coordinates.push_back({x, y});

        x_max = std::max(x, x_max);
        y_max = std::max(y, y_max);
        x_min = std::min(x, x_min);
        y_min = std::min(y, y_min);
    }

    *out_x_min = static_cast<int>(std::round(x_min));
    *out_y_min = static_cast<int>(std::round(y_min));
    *out_width = static_cast<int>(std::round(x_max - x_min));
    *out_height = static_cast<int>(std::round(y_max - y_min));

    return coordinates;
}

std::pair<Mask::PathCoordinate, double>
Mask::translation_and_scaling(const int image_width, const int image_height,
                              const int mask_x, const int mask_y,
                              int *mask_width, int *mask_height) const {
    // How much bigger is the image relative to the path in each dimension?
    auto ratio_x = static_cast<double>(image_width) / *mask_width;
    auto ratio_y = static_cast<double>(image_height) / *mask_height;

    // Of the scaling factors determined in each dimension,
    // use the smaller one; otherwise portions of the path
    // is outside the viewport.
    double scale = std::min(ratio_x, ratio_y);

    // Calculate the bounding box parameters
    // after the path has been scaled relative to the origin
    // but before any subsequent translations have been applied
    double scaled_mask_x = mask_x * scale;
    double scaled_mask_y = mask_y * scale;
    double scaled_mask_width = *mask_width * scale;
    double scaled_mask_height = *mask_height * scale;

    // Calculate the centre points of the scaled but untranslated path
    // as well as of the image
    double scaled_mask_centre_x = scaled_mask_x + (scaled_mask_width / 2.0);
    double scaled_mask_centre_y = scaled_mask_y + (scaled_mask_height / 2.0);
    double image_centre_x = image_width / 2.0;
    double image_centre_y = image_height / 2.0;

    // Calculate translation required to centre the mask
    // on the image
    PathCoordinate mask_transl = {
        static_cast<float>(image_centre_x - scaled_mask_centre_x),
        static_cast<float>(image_centre_y - scaled_mask_centre_y)};

    *mask_width = static_cast<int>(std::round(scaled_mask_width));
    *mask_height = static_cast<int>(std::round(scaled_mask_height));

    return std::pair{mask_transl, scale};
}

std::string
Mask::transformed_path_string(const std::vector<PathCoordinate> &coordinates,
                              const PathCoordinate &transl,
                              const double scale) const {
    std::ostringstream ss;
    ss << std::fixed << std::showpoint << std::setprecision(1);

    for (size_t i = 0; i != coordinates.size(); ++i) {
        auto [x, y] = coordinates[i];

        auto prepend = i == 0 ? "M" : " L";
        ss << prepend << x * scale + transl.x << " " << y * scale + transl.y;
    }

    ss << " Z";

    return ss.str();
}

VImage Mask::process(const VImage &image) const {
    auto mask_type = query_->get<MaskType>("mask", MaskType::None);

    // Should we process the image?
    if (mask_type == MaskType::None) {
        return image;
    }

    auto n_pages = query_->get<int>("n");

    int image_width = image.width();
    int image_height = image.height();

    auto page_height =
        n_pages > 1 ? query_->get<int>("page_height") : image_height;

    const auto *preserve_aspect_ratio =
        mask_type == MaskType::Ellipse ? "none" : "xMidYMid meet";

    int x_min, y_min, mask_width, mask_height;
    auto path = svg_path_by_type(image_width, page_height, mask_type, &x_min,
                                 &y_min, &mask_width, &mask_height);

    auto mask_background = query_->get<Color>("mbg", Color::DEFAULT);

    // Internal copy, we need to re-assign a few times
    auto output_image = image;

    // Cut out first if the mask background is not opaque or when the image has
    // an alpha channel
    if (!mask_background.is_opaque() || output_image.has_alpha()) {
        std::ostringstream svg;
        svg << R"(<svg xmlns="http://www.w3.org/2000/svg")"
            << R"( xmlns:xlink="http://www.w3.org/1999/xlink")"
            << " width=\"" << image_width << "\" height=\"" << image_height
            << "\""
            << " preserveAspectRatio=\"" << preserve_aspect_ratio << "\">\n"
            << R"(<defs><path id="shape" d=")" << path << "\"/></defs>\n";
        for (int i = 0; i < n_pages; ++i) {
            svg << R"(<use xlink:href="#shape" y=")"
                << static_cast<uint64_t>(page_height) * i << "\"/>\n";
        }
        svg << "</svg>";

        auto svg_mask = svg.str();

        // We don't take a copy of the data or free it
        auto blob =
            Blob(vips_blob_new(nullptr, svg_mask.data(), svg_mask.size()));
        auto mask = VImage::svgload_buffer(
            blob.get(),
            VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));

        // Cutout via dest-in
        output_image = output_image.composite2(mask, VIPS_BLEND_MODE_DEST_IN);
    }

    // If the mask background is not completely transparent; overlay the frame
    if (!mask_background.is_transparent()) {
        std::ostringstream svg;
        svg << R"(<svg xmlns="http://www.w3.org/2000/svg")"
            << R"( xmlns:xlink="http://www.w3.org/1999/xlink")"
            << " width=\"" << image_width << "\" height=\"" << image_height
            << "\""
            << " preserveAspectRatio=\"" << preserve_aspect_ratio << "\">\n"
            << R"(<defs><path id="shape" d=")" << path << " M0 0 h"
            << image_width << " v" << page_height << " h-" << image_width
            << R"( Z" fill-rule="evenodd" )"
            << "fill=\"" << mask_background.to_string() << "\"/></defs>\n";
        for (int i = 0; i < n_pages; ++i) {
            svg << R"(<use xlink:href="#shape" y=")"
                << static_cast<uint64_t>(page_height) * i << "\"/>\n";
        }
        svg << "</svg>";

        auto svg_frame = svg.str();

        // We don't take a copy of the data or free it
        auto blob =
            Blob(vips_blob_new(nullptr, svg_frame.data(), svg_frame.size()));
        auto frame = VImage::svgload_buffer(
            blob.get(),
            VImage::option()->set("access", VIPS_ACCESS_SEQUENTIAL));

        // Ensure image to composite is premultiplied sRGB
        frame = frame.premultiply();

        // Alpha composite src over dst
        output_image = output_image.composite2(
            frame, VIPS_BLEND_MODE_OVER,
            VImage::option()->set("premultiplied", true));
    }

    // Crop the image to the mask dimensions:
    //  - if the mask type is not an ellipse
    //  - trimming is needed
    if (mask_type != MaskType::Ellipse &&
        (mask_width < image_width || mask_height < page_height) &&
        query_->get<bool>("mtrim", false)) {
        auto left =
            static_cast<int>(std::round((image_width - mask_width) / 2.0));
        auto top =
            static_cast<int>(std::round((page_height - mask_height) / 2.0));

        if (n_pages > 1) {
            // Update the page height
            query_->update("page_height", mask_height);

            return utils::crop_multi_page(output_image, config_.process_timeout,
                                          left, top, mask_width, mask_height,
                                          n_pages, page_height);
        }

        return output_image.extract_area(left, top, mask_width, mask_height);
    }

    return output_image;
}

}  // namespace weserv::api::processors
