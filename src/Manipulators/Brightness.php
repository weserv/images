<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $bri
 */
class Brightness extends BaseManipulator
{
    /**
     * Perform brightness image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $brightness = $this->getBrightness();

        if ($brightness !== 0) {
            $range = Utils::mapToRange($brightness, -100, 100, -255, 255);

            // Edit the brightness
            $image = $image->linear([1, 1, 1], [$range, $range, $range]);
        }

        return $image;
    }

    /**
     * Resolve brightness amount.
     *
     * @return int The resolved brightness amount.
     */
    public function getBrightness(): int
    {
        if (!preg_match('/^-*[0-9]+$/', $this->bri)) {
            return 0;
        }

        if ($this->bri < -100 or $this->bri > 100) {
            return 0;
        }

        return (int)$this->bri;
    }
}
