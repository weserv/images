<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $con
 */
class Contrast extends BaseManipulator
{
    /**
     * Perform contrast image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $contrast = $this->getContrast();

        if ($contrast !== null) {
            // TODO Make the contrast manipulator working
            //$image->contrast($contrast);
        }

        return $image;
    }

    /**
     * Resolve contrast amount.
     * @return string The resolved contrast amount.
     */
    public function getContrast()
    {
        if (!preg_match('/^-*[0-9]+$/', $this->con)) {
            return;
        }

        if ($this->con < -100 || $this->con > 100) {
            return;
        }

        return (int)$this->con;
    }
}
