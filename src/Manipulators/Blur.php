<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $blur
 */
class Blur extends BaseManipulator
{
    /**
     * Perform blur image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $blur = $this->getBlur();

        if ($blur !== null) {
            $image = $image->gaussblur($blur);
        }

        return $image;
    }

    /**
     * Resolve blur amount.
     * @return string The resolved blur amount.
     */
    public function getBlur()
    {
        if (!preg_match('/^[0-9]\.*[0-9]*$/', $this->blur)) {
            return;
        }

        if ($this->blur < 0.0 || $this->blur > 100.0) {
            return;
        }

        return (double)$this->blur;
    }
}
