<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $bri
 */
class Brightness extends BaseManipulator
{
    /**
     * Perform brightness image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $brightness = $this->getBrightness();

        if ($brightness !== null) {
            // TODO Make the brightness manipulator working
            // Move the image to another colour space. sRGB image -> LCh (lightness, chroma, hue)
            /*$lch = $image->sRGB2XYZ()->XYZ2Lab()->Lab2LCh();

            // Edit the lightness
            $lightness = $lch->lin([$brightness, 1, 1], [0, 0, 0]);

            // And move it back to sRGB
            $image = $lightness->LCh2Lab()->Lab2XYZ()->XYZ2sRGB();*/
        }

        return $image;
    }

    /**
     * Resolve brightness amount.
     * @return string The resolved brightness amount.
     */
    public function getBrightness()
    {
        if (!preg_match('/^-*[0-9]+$/', $this->bri)) {
            return;
        }

        if ($this->bri < -100 or $this->bri > 100) {
            return;
        }

        return (int)$this->bri;
    }
}
