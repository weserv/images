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
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        // Resolve orientation
        $orientation = $this->getOrientation();

        // Calculate angle of rotation
        list($rotation, $flip, $flop) = Utils::calculateRotationAndFlip($orientation, $image);

        // Rotate if required.
        if ($rotation != 0) {
            $image = $image->rot('d' . $rotation);
        }

        // Flip (mirror about Y axis) if required.
        if ($flip) {
            $image = $image->flipver();
        }

        // Flop (mirror about X axis) if required.
        if ($flop) {
            $image = $image->fliphor();
        }

        return $image;
    }

    /**
     * Resolve orientation.
     *
     * @return int The resolved orientation.
     */
    public function getOrientation(): int
    {
        if (in_array($this->or, ['90', '180', '270'], true)) {
            return (int)$this->or;
        }

        return -1;
    }
}
