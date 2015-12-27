<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

/**
 * @property string $gam
 */
class Gamma extends BaseManipulator
{
    /**
     * Perform gamma image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $gamma = $this->getGamma();

        if ($gamma) {
            $image->gamma($gamma);
        }

        return $image;
    }

    /**
     * Resolve gamma amount.
     * @return string The resolved gamma amount.
     */
    public function getGamma()
    {
        if (!preg_match('/^[0-9]\.*[0-9]*$/', $this->gam)) {
            return;
        }

        if ($this->gam < 0.1 or $this->gam > 9.99) {
            return;
        }

        return (double)$this->gam;
    }
}
