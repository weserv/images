<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageProcessingException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

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

            list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMaskShape($width, $height, $shape);

            // Enlarge overlay mask, if required
            if ($maskWidth < $width || $maskHeight < $height) {
                $mask = $mask->embed($xMin, $yMin, $width, $height, [
                    'extend' => 'background',
                    'background' => [0.0, 0.0, 0.0, 0.0]
                ]);
            }

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
    private function getMaskShape(int $width, int $height, string $shape): array
    {
        $min = min($width, $height);
        $outerRadius = $min / 2;
        $midX = $width / 2;
        $midY = $height / 2;

        switch ($shape) {
            // TODO There's isn't a `draw_ellipse` function in libvips. Find out how to make an ellipse shape.
            /*case 'ellipse':
                $xMin = 0;
                $yMin = 0;

                break;*/
            case 'hexagon':
                // Hexagon
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
                    $midX,
                    $midY,
                    6,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon':
                // Pentagon
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'pentagon-180':
                // Pentagon tilted upside down
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
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
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
                    $midX,
                    $midY,
                    4,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'star':
                // 5 point star
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
                    $midX,
                    $midY,
                    5,
                    $outerRadius,
                    $outerRadius * .382
                );
                break;
            case 'triangle':
                // Triangle
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
                    $midX,
                    $midY,
                    3,
                    $outerRadius,
                    $outerRadius
                );
                break;
            case 'triangle-180':
                // Triangle upside down
                list($mask, $xMin, $yMin, $maskWidth, $maskHeight) = $this->getMask(
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

                // Make a transparent mask matching the origin image dimensions.
                $mask = Image::black($width, $height, ['bands' => 4]);

                // Draw a filled black circle on the mask.
                $mask = $mask->draw_circle(255, $midX - $xMin, $midY - $yMin, $outerRadius, ["fill" => true]);
                break;
        }

        return [$mask, $xMin, $yMin, $maskWidth, $maskHeight];
    }

    /**
     * Inspired by this GitHub gist: https://gist.github.com/Jondeen/5a7043a7de7bf4cbdc4f and
     * this JSFiddle: http://jsfiddle.net/tohan/8vwjn4cx/
     *
     * @param  int|float $midX midX
     * @param  int|float $midY midY
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
    private function getMask($midX, $midY, $points, $outerRadius, $innerRadius, $initialAngle = 0): array
    {
        $xArr = [];
        $yArr = [];

        if ($innerRadius !== $outerRadius) {
            $points *= 2;
        }
        for ($i = 0; $i <= $points; $i++) {
            $angle = $i * 2 * pi() / $points - pi() / 2 + $initialAngle;
            $radius = $i % 2 === 0 ? $outerRadius : $innerRadius;

            $xArr[] = round($midX + $radius * cos($angle));
            $yArr[] = round($midY + $radius * sin($angle));
        }

        $xMin = min($xArr);
        $yMin = min($yArr);
        $xMax = max($xArr);
        $yMax = max($yArr);

        $width = $xMax - $xMin;
        $height = $yMax - $yMin;

        $xyz = Image::xyz($width, $height)->bandsplit(['n' => 1]);

        // Normalize by cutting off lower than minimum, higher than maximum:
        $coords = array_map(function ($x, $y) use ($xMin, $yMin) {
            return [$x - $xMin, $y - $yMin];
        }, $xArr, $yArr);

        // Helper vars to make xy-refs more semantic
        $x = 0;
        $y = 1;

        $logic = null;

        for ($i2 = 0; $i2 < $points; $i2++) {
            $currY = $coords[$i2][$y];
            $nextY = $coords[$i2 + 1][$y];

            // Rising or lowering:
            $risingPastPoint = $xyz[$y]->moreEq($currY)->andimage($xyz[$y]->less($nextY));
            $loweringPastPoint = $xyz[$y]->less($currY)->andimage($xyz[$y]->moreEq($nextY));
            $riseOrLow = $risingPastPoint->orimage($loweringPastPoint);

            $currX = $coords[$i2][$x];
            $nextX = $coords[$i2 + 1][$x];

            // On diagonal side:
            $diagonalSide = $xyz[$x]->less(
                $xyz[$y]
                    ->subtract($currY)
                    ->divide($nextY - $currY)
                    ->multiply($nextX - $currX)
                    ->add($currX)
            )->eorimage(-1);

            // Together:
            $test = $riseOrLow->andimage($diagonalSide);

            if ($logic == null) {
                $logic = $test;
            } else {
                $logic = $logic->eorimage($test);
            }
        }

        return [$logic/*->eorimage(-1)*/, $xMin, $yMin, $width, $height];
    }
}