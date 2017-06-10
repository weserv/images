<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Image;

/**
 * @property string $t
 * @property string $w
 * @property string $h
 * @property string $a
 * @property string $bg
 * @property bool $hasAlpha
 * @property bool $is16Bit
 */
class Letterbox extends BaseManipulator
{
    /**
     * Perform letterbox image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $width = $this->w;
        $height = $this->h;

        if (($image->width !== $width || $image->height !== $height) && $this->t === 'letterbox') {
            // Default: black
            $backgroundColor = [
                0,
                0,
                0,
                0
            ];

            if ($this->bg) {
                $backgroundColor = (new Color($this->bg))->toRGBA();
            }

            // Scale up 8-bit values to match 16-bit input image
            $multiplier = $this->is16Bit ? 256 : 1;

            // Create background colour
            $background = [
                $multiplier * $backgroundColor[0],
                $multiplier * $backgroundColor[1],
                $multiplier * $backgroundColor[2]
            ];

            if ($image->bands < 3) {
                // Convert sRGB to greyscale
                $background = [
                    $multiplier * (
                        (0.2126 * $backgroundColor[0]) +
                        (0.7152 * $backgroundColor[1]) +
                        (0.0722 * $backgroundColor[2])
                    )
                ];
            }

            $hasAlpha = $this->hasAlpha;

            // Add alpha channel to background colour
            if ($hasAlpha || $backgroundColor[3] < 255) {
                $background[] = $backgroundColor[3] * $multiplier;
            }

            // Add non-transparent alpha channel, if required
            if (!$hasAlpha && $backgroundColor[3] < 255) {
                $result = $image->newFromImage(255 * $multiplier);
                $image = $image->bandjoin($result);

                // Image has now a alpha channel. Useful for the next manipulators.
                $this->hasAlpha = true;
            }

            $left = (int)round(($width - $image->width) / 2);
            $top = (int)round(($height - $image->height) / 2);
            $image = $image->embed(
                $left,
                $top,
                $width,
                $height,
                ['extend' => Extend::BACKGROUND, 'background' => $background]
            );
        }

        return $image;
    }
}
