#pragma once

#include "../enums.h"
#include "base.h"

#include <string>
#include <vector>

namespace weserv::api::processors {

class Mask : ImageProcessor {
 public:
    using ImageProcessor::ImageProcessor;

    struct PathCoordinate {
        float x, y;
    };

    VImage process(const VImage &image) const override;

 private:
    /**
     * Get the SVG mask path by type.
     * @param width Image width.
     * @param height Image width.
     * @param mask Type mask.
     * @param out_x_min Left edge of mask.
     * @param out_y_min Top edge of mask.
     * @param out_width Mask width.
     * @param out_height Mask height.
     */
    std::string svg_path_by_type(int width, int height,
                                 const enums::MaskType &mask,
                                 int *out_x_min, int *out_y_min,
                                 int *out_width, int *out_height) const;

    /**
     * Formula from https://mathworld.wolfram.com/HeartCurve.html
     * @param cx The x coordinate of the center of the image.
     * @param cy The y coordinate of the center of the image.
     * @return The circle represented as SVG path.
     */
    std::vector<PathCoordinate> heart_path(float cx, float cy,
                                           int *out_x_min, int *out_y_min,
                                           int *out_width, int *out_height) const;

    /**
     * Calculate the transformation, i.e. the translation and scaling, required
     * to get the mask to fill the image area.
     * @param image_width Image width.
     * @param image_height Image height.
     * @param mask_width Mask width.
     * @param mask_height Mask height.
     * @param mask_x Left edge of mask.
     * @param mask_y Top edge of mask.
     * @return Transformation coordinate and scale pair.
     */
    std::pair<PathCoordinate, double>
    translation_and_scaling(int image_width, int image_height,
                            int mask_x, int mask_y,
                            int *mask_width, int *mask_height) const;

    /**
     * Get the transformed path "d" attribute.
     * @param coordinates Path coordinates.
     * @param transl x, y-coordinate transformation.
     * @param scale Scale factor.
     * @return Transformed path as string.
     */
    std::string
    transformed_path_string(const std::vector<PathCoordinate> &coordinates,
                            const PathCoordinate &transl, double scale) const;

    /**
     * Generates an circle SVG path.
     * See also: https://stackoverflow.com/a/10477334
     * @param cx The x coordinate of the center of the circle.
     * @param cy The y coordinate of the center of the circle.
     * @param r The radius of the circle.
     * @return The circle represented as SVG path.
     */
    std::string svg_circle_path(float cx, float cy, float r) const;

    /**
     * Generates an ellipse SVG path.
     * @param cx The x coordinate of the center of the ellipse.
     * @param cy The y coordinate of the center of the ellipse.
     * @param rx The horizontal radius.
     * @param ry The vertical radius.
     * @return The ellipse represented as SVG path.
     */
    std::string svg_ellipse_path(float cx, float cy, float rx, float ry) const;
};

}  // namespace weserv::api::processors
