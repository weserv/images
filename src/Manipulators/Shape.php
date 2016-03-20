<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

/**
 * @property string $shape
 * @property string $circle
 */
class Shape extends BaseManipulator
{
    /**
     * Perform shape image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $shape = $this->getShape();

        if ($shape !== null) {
            $width = $image->getWidth();
            $height = $image->getHeight();
            $min = min($width, $height);

            if ($image->getDriver()->getDriverName() == 'Gd') {
                if ($shape === 'circle' || $shape === 'ellipse') {
                    // Mask is slow on GD driver so we are using a different approach
                    $img = $image->getCore();

                    // Create a black image with a transparent ellipse, and merge with destination
                    $mask = imagecreatetruecolor($width, $height);
                    $maskTransparent = imagecolorallocate($mask, 255, 0, 255);
                    imagecolortransparent($mask, $maskTransparent);
                    imagefilledellipse($mask, $width / 2, $height / 2, $width, $height, $maskTransparent);
                    imagecopymerge($img, $mask, 0, 0, 0, 0, $width, $height, 100);
                    // Fill each corners of destination image with transparency
                    $dstTransparent = imagecolorallocatealpha($img, 255, 0, 255, 127);
                    imagefill($img, 0, 0, $dstTransparent);
                    imagefill($img, $width - 1, 0, $dstTransparent);
                    imagefill($img, 0, $height - 1, $dstTransparent);
                    imagefill($img, $width - 1, $height - 1, $dstTransparent);

                    $image->setCore($img);
                }
                // TODO Support for ellipse, circle (circle now is ellipse), triangle, square, pentagon, stars for GD driver
            } else {
                $outerRadius = $min / 2;

                $mask = null;

                if ($shape === 'ellipse') {
                    $mask = $this->makeEllipseMaskImage($image, $width, $height);
                }
                if ($shape === 'triangle-180') {
                    // Triangle upside down
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 3, $outerRadius, $outerRadius, pi());
                }
                if ($shape === 'triangle') {
                    // Triangle normal
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 3, $outerRadius, $outerRadius, 0);
                }
                if ($shape === 'square') {
                    // Square tilted 45 degrees
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 4, $outerRadius, $outerRadius, 0);
                }
                if ($shape === 'pentagon-180') {
                    // Pentagon tilted upside down
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 5, $outerRadius, $outerRadius, pi());
                }
                if ($shape === 'pentagon') {
                    // Pentagon normal
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 5, $outerRadius, $outerRadius, 0);
                }
                if ($shape === 'star-3') {
                    // 3 point star
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 3, $outerRadius, $outerRadius * .191, 0);
                }
                if ($shape === 'star-4') {
                    // 4 point star
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 4, $outerRadius, $outerRadius * .382, 0);
                }
                if ($shape === 'star' || $shape === 'star-5') {
                    // 5 point star
                    $mask = $this->makeShapeMaskImage($image, $width, $height, 5, $outerRadius, $outerRadius * .382, 0);
                }
                if ($shape === 'circle') {
                    $mask = $this->makeCircleMaskImage($image, $width, $height);
                }


                if ($mask !== null) {
                    $image = $image->mask($mask, false);

                    if ($shape !== 'ellipse') {
                        $min = min($width, $height);
                        $image->crop($min, $min);

                        // TODO Should we trim it?
                        $image->trim();
                    }
                }
            }
        }

        return $image;
    }

    /**
     * Resolve shape
     * @return string The resolved shape.
     */
    public function getShape()
    {
        if (in_array($this->shape, [
            'circle',
            'ellipse',
            'star',
            'star-3',
            'star-4',
            'star-5',
            'triangle',
            'triangle-180',
            'square',
            'pentagon',
            'pentagon-180'
        ], true)) {
            return $this->shape;
        }

        if ($this->circle !== null) {
            return 'circle';
        }

        return null;
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @return Image
     */
    private function makeEllipseMaskImage(Image $image, $width, $height)
    {

        $ellipse = $image->getDriver()->newImage($width, $height, '#000000');

        $ellipse = $ellipse->ellipse($width, $height, $width / 2, $height / 2, function ($draw) {
            $draw->background('#ffffff');
        });

        return $ellipse;
    }


    /**
     * PHP Version of this: http://jsfiddle.net/tohan/8vwjn4cx/
     *
     * @param Image $image
     * @param int $width
     * @param int $height
     * @param int $points number of points (or number of sides for polygons)
     * @param int $radius1 "outer" radius of the star
     * @param int $radius2 "inner" radius of the star (if equal to radius1, a polygon is drawn)
     * @param int $angle0 initial angle (clockwise), by default, stars and polygons are 'pointing' up
     *
     * @return Image
     */
    function makeShapeMaskImage(Image $image, $width, $height, $points, $radius1, $radius2, $angle0)
    {
        $midX = $width / 2;
        $midY = $height / 2;

        /**
         * @var array $points : polygons array
         */
        $pointsArr = array();

        if ($radius2 !== $radius1) {
            $points = 2 * $points;
        }

        for ($i = 0; $i <= $points; $i++) {
            $angle = $i * 2 * pi() / $points - pi() / 2 + $angle0;
            $radius = $i % 2 === 0 ? $radius1 : $radius2;

            $pointsArr[] = round($midX + $radius * cos($angle));
            $pointsArr[] = round($midY + $radius * sin($angle));
        }

        $shape = $image->getDriver()->newImage($width, $height, '#000000');

        $shape = $shape->polygon($pointsArr, function ($draw) {
            $draw->background('#ffffff');
        });

        return $shape;
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @return Image
     */
    private function makeCircleMaskImage(Image $image, $width, $height)
    {
        $min = min($width, $height);

        $circle = $image->getDriver()->newImage($width, $height, '#000000');

        $circle = $circle->circle($min, $width / 2, $height / 2, function ($draw) {
            $draw->background('#ffffff');
        });

        return $circle;
    }

}