<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $gam
 * @property bool $hasAlpha
 */
class Gamma extends BaseManipulator
{
    /**
     * Perform gamma image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if (!$this->gam) {
            return $image;
        }

        $gamma = $this->getGamma();

        if ($this->hasAlpha) {
            // Separate alpha channel
            $imageWithoutAlpha = $image->extract_band(0, ['n' => $image->bands - 1]);
            $alpha = $image->extract_band($image->bands - 1, ['n' => 1]);
            $image = $imageWithoutAlpha->gamma(['exponent' => $gamma])->bandjoin($alpha);
        } else {
            $image = $image->gamma(['exponent' => $gamma]);
        }

        return $image;
    }

    /**
     * Resolve gamma amount.
     *
     * @return float The resolved gamma amount.
     */
    public function getGamma(): float
    {
        // Default gamma correction of 2.2 (sRGB)
        $default = 2.2;

        if (!preg_match('/^[0-9]\.*[0-9]*$/', $this->gam)) {
            return $default;
        }

        if ($this->gam < 1.0 || $this->gam > 3.0) {
            return $default;
        }

        return (float)$this->gam;
    }
}
