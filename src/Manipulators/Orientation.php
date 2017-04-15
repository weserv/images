<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $or
 */
class Orientation extends BaseManipulator
{
    /**
     * Perform orientation image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        // Rotate if required.
        $orientation = $this->or;
        if ($orientation === '90' || $orientation === '180' || $orientation === '270') {
            // Need to copy to memory, we have to stay seq.
            $image = $image->copyMemory()->rot('d' . $orientation);
        }

        return $image;
    }
}
