<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $gam
 * @property bool $hasAlpha
 * @property bool $isPremultiplied
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
        if (!isset($this->gam)) {
            return $image;
        }

        if ($this->hasAlpha && !$this->isPremultiplied) {
            // Premultiply image alpha channel before gamma transformation to avoid
            // dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply();
            $this->isPremultiplied = true;
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

        if (!is_numeric($this->gam)) {
            return $default;
        }

        if ($this->gam < 1.0 || $this->gam > 3.0) {
            return $default;
        }

        return (float)$this->gam;
    }
}
