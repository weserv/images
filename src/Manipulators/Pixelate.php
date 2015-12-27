<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

/**
 * @property string $pixel
 */
class Pixelate extends BaseManipulator
{
    /**
     * Perform pixelate image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $pixelate = $this->getPixelate();

        if ($pixelate !== null) {
            $image->pixelate($pixelate);
        }

        return $image;
    }

    /**
     * Resolve pixelate amount.
     * @return string The resolved pixelate amount.
     */
    public function getPixelate()
    {
        if (!is_numeric($this->pixel)) {
            return;
        }

        if ($this->pixel < 0 or $this->pixel > 1000) {
            return;
        }

        return (int)$this->pixel;
    }
}
