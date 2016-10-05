<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageProcessingException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $shape
 * @property string $circle
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

            $mask = $this->getSVGShape($width, $height, $shape);

            $maskHasAlpha = Utils::hasAlpha($mask);

            if ($mask == null || (!$maskHasAlpha && $mask->bands > 1)) {
                throw new ImageProcessingException("Overlay image must have an alpha channel or one band");
            }

            $imageHasAlpha = Utils::hasAlpha($image);

            // we use the mask alpha if it has alpha
            if ($maskHasAlpha) {
                $mask = $mask->extract_band($mask->bands - 1, ['n' => 1]);
            }

            // Split image into an optional alpha
            $imageAlpha = $image->extract_band($image->bands - 1, ['n' => 1]);

            // we use the image non-alpha
            if ($imageHasAlpha) {
                $image = $image->extract_band(0, ["n", $image->bands - 1]);
            }

            // the range of the mask and the image need to match .. one could be
            // 16-bit, one 8-bit
            $imageMax = Utils::maximumImageAlpha($image->interpretation);
            $maskMax = Utils::maximumImageAlpha($mask->interpretation);

            if ($imageHasAlpha) {
                // combine the new mask and the existing alpha ... there are
                // many ways of doing this, mult is the simplest
                $mask = $mask->divide($maskMax)->multiply($imageMax)->multiply($imageAlpha / $imageMax);
            } else {
                if ($imageMax != $imageMax) {
                    // adjust the range of the mask to match the image
                    $mask = $mask->divide($maskMax)->multiply($imageMax);
                }
            }

            // append the mask to the image data ... the mask might be float now,
            // we must cast the format down to match the image data
            $image = $image->bandjoin([$mask->cast($image->format)]);
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
     * @return Image
     */
    private function getSVGShape(int $width, int $height, string $shape): Image
    {
        $xml = "<?xml version='1.0' encoding='UTF-8' standalone='no'?>";
        $svgHead = "<svg xmlns='http://www.w3.org/2000/svg' version='1.1' width='$width' height='$height' viewBox='0 0 $width $height' id='svg-$shape' shape-rendering='geometricPrecision' ";
        if ($shape == "ellipse") {
            $svgHead .= "preserveAspectRatio='none'>";
        } else {
            $svgHead .= "preserveAspectRatio='xMidYMid meet'>";
        }
        $min = min($width, $height);
        $outerRadius = $min / 2;
        $midX = $width / 2;
        $midY = $height / 2;

        switch ($shape) {
            case 'ellipse':
                // Ellipse
                $svgShape = "<ellipse cx='$midX' cy='$midY' rx='$midX' ry='$midY'/>";
                break;
            case 'hexagon':
                // Hexagon
                $svgShape = $this->getShapePath($midX, $midY, 6, $outerRadius, $outerRadius);
                break;
            case 'pentagon':
                // Pentagon
                $svgShape = $this->getShapePath($midX, $midY, 5, $outerRadius, $outerRadius);
                break;
            case 'pentagon-180':
                // Pentagon tilted upside down
                $svgShape = $this->getShapePath($midX, $midY, 5, $outerRadius, $outerRadius, pi());
                break;
            case 'square':
                // Square tilted 45 degrees
                $svgShape = $this->getShapePath($midX, $midY, 4, $outerRadius, $outerRadius);
                break;
            case 'square-rounded':
                // Square with border-radius of 5%
                $svgShape = "<rect x='0' y='0' width='100%' height='100%' rx='5%' ry='5%'/>";
                break;
            case 'star':
                // 5 point star
                $svgShape = $this->getShapePath($midX, $midY, 5, $outerRadius, $outerRadius * .382);
                break;
            case 'triangle':
                // Triangle
                $svgShape = $this->getShapePath($midX, $midY, 3, $outerRadius, $outerRadius);
                break;
            case 'triangle-180':
                // Triangle upside down
                $svgShape = $this->getShapePath($midX, $midY, 3, $outerRadius, $outerRadius, pi());
                break;
            case 'circle':
            default:
                // Circle (default)
                $svgShape = "<circle r='$outerRadius' cx='$midX' cy='$midY'/>";
                break;
        }
        $svgTail = '</svg>';

        // TODO: SVG loading is slow.
        // Find alternatives such as $image->draw_circle(255, $midX, $midY, $outerRadius, ["fill" => true]);
        return Image::newFromBuffer($xml . $svgHead . $svgShape . $svgTail);
    }

    /**
     * Inspired by this JSFiddle: http://jsfiddle.net/tohan/8vwjn4cx/
     * modified to support SVG paths
     *
     * @param  int|float $x midX
     * @param  int|float $y midY
     * @param  int|float $points number of points (or number of sides for polygons)
     * @param  int|float $outerRadius "outer" radius of the star
     * @param  int|float $innerRadius "inner" radius of the star (if equal to outerRadius, a polygon is drawn)
     * @param  int|float $initialAngle (optional) initial angle (clockwise),
     *      by default, stars and polygons are 'pointing' up
     *
     * @return string The SVG path
     */
    private function getShapePath($x, $y, $points, $outerRadius, $innerRadius, $initialAngle = 0): string
    {
        $path = "";
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

            $path .= round($x + $radius * cos($angle)) . " " . round($y + $radius * sin($angle));
        }

        $path .= " Z";

        return "<path d='" . $path . "'/>";
    }
}
