<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $or
 */
class Orientation extends BaseManipulator
{
    /**
     * Perform orientation image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        // Resolve orientation
        $orientation = $this->getOrientation();

        // Calculate angle of rotation
        list($rotation, $flip, $flop) = Utils::calculateRotationAndFlip($orientation, $image);

        if ($rotation != Utils::VIPS_ANGLE_D0) {
            $image = $image->rot($rotation);
            Utils::removeExifOrientation($image);
        }

        // Flip (mirror about Y axis)
        if ($flip) {
            $image = $image->flipver();
            Utils::removeExifOrientation($image);
        }

        // Flop (mirror about X axis)
        if ($flop) {
            $image = $image->fliphor();
            Utils::removeExifOrientation($image);
        }

        return $image;
    }

    /**
     * Resolve orientation.
     * @return string The resolved orientation.
     */
    public function getOrientation()
    {
        if (in_array($this->or, ['90', '180', '270'], true)) {
            return (int)$this->or;
        }

        return -1;
    }
}
