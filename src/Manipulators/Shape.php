<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageProcessingException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;
use phpDocumentor\Descriptor\PackageDescriptor;

/**
 * @property string $shape
 * @property string $circle
 * @property bool $hasAlpha
 * @property int $maxAlpha
 */
class Shape extends BaseManipulator
{
    /**
     * Perform shape image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @throws ImageProcessingException for errors that occur during the processing of a Image
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $shape = $this->getShape();

        if ($shape !== null) {
            $width = $image->width;
            $height = $image->height;

            list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getSVGShape($width, $height, $shape);

            $maskHasAlpha = Utils::hasAlpha($mask);

            if ($mask === null || (!$maskHasAlpha && $mask->bands > 1)) {
                throw new ImageProcessingException('Overlay image must have an alpha channel or one band');
            }

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
            $imageMax = $this->maxAlpha;
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
                'square-rounded',
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
     *      *Mask image*,
     *      *Left edge of mask*,
     *      *Top edge of mask*,
     *      *Mask width*,
     *      *Mask height*
     * ]
     */
    private function getSVGShape(int $width, int $height, string $shape): array
    {
        $xml = "<?xml version='1.0' encoding='UTF-8' standalone='no'?>";
        $svgHead = "<svg xmlns='http://www.w3.org/2000/svg' version='1.1'";
        $svgHead .= " width='$width' height='$height'";
        $svgHead .= " viewBox='0 0 $width $height' id='svg-$shape' shape-rendering='geometricPrecision'";
        if ($shape == 'ellipse') {
            $svgHead .= " preserveAspectRatio='none'>";
        } else {
            $svgHead .= " preserveAspectRatio='xMidYMid meet'>";
        }
        $min = min($width, $height);
        $outerRadius = $min / 2;
        $midX = $width / 2;
        $midY = $height / 2;

        switch ($shape) {
            case 'ellipse':
                $xMin = 0;
                $yMin = 0;
                // Ellipse
                $svgShape = "<ellipse cx='$midX' cy='$midY' rx='$midX' ry='$midY'/>";
                break;
            case 'hexagon':
                // Hexagon
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
                    $midX,
                    $midY,
                    6,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon':
                // Pentagon
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon-180':
                // Pentagon tilted upside down
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
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
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
                    $midX,
                    $midY,
                    4,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'square-rounded':
                $xMin = 0;
                $yMin = 0;
                // Square with border-radius of 5%
                $svgShape = "<rect x='0' y='0' width='100%' height='100%' rx='5%' ry='5%'/>";
                break;
            case 'star':
                // 5 point star
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius * .382
                );
                break;
            case 'triangle':
                // Triangle
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
                    $midX,
                    $midY,
                    3,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'triangle-180':
                // Triangle upside down
                list($svgShape, $xMin, $yMin, $width, $height) = $this->getShapePath(
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
                $width = $min;
                $height = $min;

                // Circle (default)
                $svgShape = "<circle r='$outerRadius' cx='$midX' cy='$midY'/>";
                break;
        }
        $svgTail = '</svg>';

        $svg = $xml . $svgHead . $svgShape . $svgTail;

        // TODO: SVG loading is slow:
        // Find alternatives such as $image->draw_circle(255, $midX, $midY, $outerRadius, ['fill' => true]);
        return [Image::newFromBuffer($svg), $xMin, $yMin, $width, $height];
    }

    /**
     * Inspired by this JSFiddle: http://jsfiddle.net/tohan/8vwjn4cx/
     * modified to support SVG paths
     *
     * @param  int|float $x midX
     * @param  int|float $y midY
     * @param  int|float $points number of points (or number of sides for polygons)
     * @param  int|float $outerRadius 'outer' radius of the star
     * @param  int|float $innerRadius 'inner' radius of the star (if equal to outerRadius, a polygon is drawn)
     * @param  int|float $initialAngle (optional) initial angle (clockwise),
     *      by default, stars and polygons are 'pointing' up
     *
     * @return array [
     *      *The SVG path*,
     *      *Left edge of mask*,
     *      *Top edge of mask*,
     *      *Mask width*,
     *      *Mask height*
     * ]
     */
    private function getShapePath($x, $y, $points, $outerRadius, $innerRadius, $initialAngle = 0): array
    {
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

            $x2 = round($x + $radius * cos($angle));
            $y2 = round($y + $radius * sin($angle));

            $X[] = $x2;
            $Y[] = $y2;

            $path .= $x2 . ' ' . $y2;
        }

        $path .= ' Z';

        $xMin = min($X);
        $yMin = min($Y);
        $xMax = max($X);
        $yMax = max($Y);
        $width = $xMax - $xMin;
        $height = $yMax - $yMin;

        /*$cX = $xMin + ($width / 2);
        $cY = $yMin + ($height / 2);*/

        return ["<path d='$path'/>", $xMin, $yMin, $width, $height];
    }
}
