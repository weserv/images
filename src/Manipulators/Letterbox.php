<?php

namespace Weserv\Images\Manipulators;

use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Image;

/**
 * @property string $t
 * @property int $w
 * @property int $h
 * @property string $a
 * @property string $bg
 */
class Letterbox extends BaseManipulator
{
    /**
     * Perform letterbox image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function run(Image $image): Image
    {
        $width = $this->w;
        $height = $this->h;

        if (($image->width !== $width || $image->height !== $height) && $this->t === 'letterbox') {
            // Always letterbox with a transparent background;
            // the background manipulator will handle the background color.
            $background = array_fill(0, $image->bands, 0);

            // Add non-transparent alpha channel, if required
            if (!$image->hasAlpha()) {
                $image = $image->bandjoin(255);
                $background[] = 0;
            }

            $left = (int)round(($width - $image->width) / 2);
            $top = (int)round(($height - $image->height) / 2);
            $image = $image->embed($left, $top, $width, $height, [
                'extend' => Extend::BACKGROUND,
                'background' => $background
            ]);
        }

        return $image;
    }
}
