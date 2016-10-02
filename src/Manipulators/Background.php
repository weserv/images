<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $bg
 */
class Background extends BaseManipulator
{
    /**
     * Perform background image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        if ($this->bg == null) {
            return $image;
        }

        $backgroundColor = (new Color($this->bg))->formatted();

        if ($backgroundColor && Utils::hasAlpha($image)) {
            $interpretation = $image->interpretation;
            // Scale up 8-bit values to match 16-bit input image
            $multiplier = Utils::is16Bit($interpretation) ? 256 : 1;

            // Background colour
            $background = [
                $backgroundColor[0] * $multiplier,
                $backgroundColor[1] * $multiplier,
                $backgroundColor[2] * $multiplier
            ];

            $image = $image->flatten([
                "background" => $background,
                "max_alpha" => Utils::maximumImageAlpha($interpretation)
            ]);
        }

        return $image;
    }
}
