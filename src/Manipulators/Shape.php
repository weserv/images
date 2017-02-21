<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $shape
 * @property string $circle
 * @property bool $hasAlpha
 * @property string $accessMethod
 */
class Shape extends BaseManipulator
{
    /**
     * Perform shape image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $shape = $this->getShape();

        if ($shape !== null) {
            $width = $image->width;
            $height = $image->height;

            list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMaskShape($width, $height, $shape);

            $maskHasAlpha = Utils::hasAlpha($mask);

            $imageHasAlpha = $this->hasAlpha;

            // we use the mask alpha if it has alpha
            if ($maskHasAlpha) {
                $mask = $mask->extract_band($mask->bands - 1, ['n' => 1]);
            }

            // Split image into an optional alpha
            $imageAlpha = $image->extract_band($image->bands - 1, ['n' => 1]);

            // we use the image non-alpha
            if ($imageHasAlpha) {
                $image = $image->extract_band(0, ['n' => $image->bands - 1]);
            }

            // the range of the mask and the image need to match .. one could be
            // 16-bit, one 8-bit
            $imageMax = Utils::maximumImageAlpha($image->interpretation);
            $maskMax = Utils::maximumImageAlpha($mask->interpretation);

            if ($imageHasAlpha) {
                // combine the new mask and the existing alpha ... there are
                // many ways of doing this, mult is the simplest
                $mask = $mask->divide($maskMax)->multiply($imageAlpha->divide($imageMax))->multiply($imageMax);
            } else {
                if ($imageMax != $maskMax) {
                    // adjust the range of the mask to match the image
                    $mask = $mask->divide($maskMax)->multiply($imageMax);
                }
            }

            // append the mask to the image data ... the mask might be float now,
            // we must cast the format down to match the image data
            $image = $image->bandjoin([$mask->cast($image->format)]);

            // If mask dimensions is less than the image dimensions crop the image to the mask dimensions.
            // Removes unnecessary white space.
            if ($maskWidth < $width || $maskHeight < $height) {
                $image = $image->extract_area($xMin, $yMin, $maskWidth, $maskHeight);
            }

            // Image has now a alpha channel. Useful for the next manipulators.
            $this->hasAlpha = true;
        }

        return $image;
    }

    /**
     * Resolve shape
     *
     * @return string|null The resolved shape.
     */
    public function getShape()
    {
        if (in_array(
            $this->shape,
            [
                'circle',
                'ellipse',
                'hexagon',
                'pentagon',
                'pentagon-180',
                'square',
                'star',
                'triangle',
                'triangle-180',
            ],
            true
        )) {
            return $this->shape;
        }

        if ($this->circle !== null) {
            return 'circle';
        }

        return null;
    }

    /**
     * @param int $width
     * @param int $height
     * @param string $shape
     *
     * @return array [
     *      *SVG image Mask*,
     *      *Left edge of mask*,
     *      *Top edge of mask*,
     *      *Mask width*,
     *      *Mask height*
     * ]
     */
    private function getMaskShape(int $width, int $height, string $shape): array
    {
        $preserveAspectRatio = $shape == 'ellipse' ? 'none' : 'xMidYMid meet';

        $svgTemplate = <<<SVG
<?xml version='1.0' encoding='UTF-8' standalone='no'?>
<svg xmlns='http://www.w3.org/2000/svg' version='1.1' width='$width' height='$height' viewBox='0 0 $width $height' 
shape-rendering='geometricPrecision' preserveAspectRatio='$preserveAspectRatio'> 
%s
</svg>
SVG;

        $min = min($width, $height);
        $outerRadius = $min / 2;
        $midX = $width / 2;
        $midY = $height / 2;

        switch ($shape) {
            case 'ellipse':
                // Ellipse
                $xMin = 0;
                $yMin = 0;

                $maskWidth = $width;
                $maskHeight = $height;

                $svg = sprintf($svgTemplate, "<ellipse cx='$midX' cy='$midY' rx='$midX' ry='$midY'/>");

                $mask = Image::newFromBuffer($svg, '', [
                    'dpi' => 72,
                    'access' => $this->accessMethod,
                ]);
                break;
            case 'hexagon':
                // Hexagon
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    6,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon':
                // Pentagon
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon-180':
                // Pentagon tilted upside down
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius,
                    pi()
                );
                break;
            case 'square':
                // Square tilted 45 degrees
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    4,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'star':
                // 5 point star
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius * .382
                );
                break;
            case 'triangle':
                // Triangle
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    3,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'triangle-180':
                // Triangle upside down
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGMask(
                    $svgTemplate,
                    $midX,
                    $midY,
                    3,
                    $outerRadius,
                    $outerRadius,
                    pi()
                );
                break;
            case 'circle':
            default:
                $xMin = $midX - $outerRadius;
                $yMin = $midY - $outerRadius;
                $maskWidth = $min;
                $maskHeight = $min;

                $svg = sprintf($svgTemplate, "<circle r='$outerRadius' cx='$midX' cy='$midY'/>");

                $mask = Image::newFromBuffer($svg, '', [
                    'dpi' => 72,
                    'access' => $this->accessMethod,
                ]);
                break;
        }

        return [$mask, $xMin, $yMin, $maskWidth, $maskHeight];
    }

    /**
     * Inspired by this JSFiddle: http://jsfiddle.net/tohan/8vwjn4cx/
     * modified to support SVG paths
     *
     * @param  string $template The SVG template
     * @param  float $midX midX
     * @param  float $midY midY
     * @param  int $points number of points (or number of sides for polygons)
     * @param  float $outerRadius 'outer' radius of the star
     * @param  float $innerRadius 'inner' radius of the star (if equal to outerRadius, a polygon is drawn)
     * @param  float $initialAngle (optional) initial angle (clockwise),
     *      by default, stars and polygons are 'pointing' up
     *
     * @return array [
     *      *SVG image mask*,
     *      *Left edge of mask*,
     *      *Top edge of mask*,
     *      *Mask width*,
     *      *Mask height*
     * ]
     */
    private function getSVGMask(
        string $template,
        float $midX,
        float $midY,
        int $points,
        float $outerRadius,
        float $innerRadius,
        float $initialAngle = 0.0
    ): array {
        $path = '';
        $X = [];
        $Y = [];
        if ($innerRadius !== $outerRadius) {
            $points *= 2;
        }
        for ($i = 0; $i <= $points; $i++) {
            $angle = $i * 2 * pi() / $points - pi() / 2 + $initialAngle;
            $radius = $i % 2 === 0 ? $outerRadius : $innerRadius;
            if ($i == 0) {
                $path = 'M';
                // If an odd number of points, add an additional point at the top of the polygon
                // -- this will shift the calculated center point of the shape so that the center point
                // of the polygon is at x,y (otherwise the center is mis-located)
                if ($points % 2 == 1) {
                    $path .= '0 ' . $radius . ' M';
                }
            } else {
                $path .= ' L';
            }
            $x = round($midX + $radius * cos($angle));
            $y = round($midY + $radius * sin($angle));
            $X[] = $x;
            $Y[] = $y;
            $path .= $x . ' ' . $y;
        }
        $path .= ' Z';
        $xMin = min($X);
        $yMin = min($Y);
        $xMax = max($X);
        $yMax = max($Y);
        $width = $xMax - $xMin;
        $height = $yMax - $yMin;

        $svg = sprintf($template, "<path d='$path'/>");

        return [
            Image::newFromBuffer($svg, '', [
                'dpi' => 72,
                'access' => $this->accessMethod,
            ]),
            $xMin,
            $yMin,
            $width,
            $height
        ];
    }
}