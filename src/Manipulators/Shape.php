<?php

namespace AndriesLouw\imagesweserv\Manipulators;

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
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $shape = $this->getShape();

        if ($shape !== null) {
            $width = $image->width;
            $height = $image->height;

            $mask = $this->makeCircleMaskImage($image, $width, $height);

            if ($mask !== null) {
                $maskHasAlpha = Utils::hasAlpha($mask);
                $imageHasAlpha = Utils::hasAlpha($image);

                // we use the mask alpha if it has alpha
                if ($maskHasAlpha) {
                    $mask = $mask->extract_band($mask->bands - 1, ['n' => 1]);;
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
                // TODO Find out why this isn't working: https://github.com/jcupitt/php-vips/issues/10
                // $image = $image->bandjoin([$mask->cast($image->format)]);
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
            // TODO Support multiple shapes
            /*'star',
            'star-3',
            'star-4',
            'star-5',
            'triangle',
            'triangle-180',
            'square',
            'pentagon',
            'pentagon-180'*/
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
        return $image->mask_ideal($width, $height, 1.0, ['uchar' => true, 'reject' => true, 'optical' => true]);
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

        return $image->mask_ideal($min, $min, 1.0, ['uchar' => true, 'reject' => true, 'optical' => true]);
    }

}