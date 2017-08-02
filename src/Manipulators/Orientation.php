<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $or
 * @property int $exifRotation
 * @property bool $flip
 * @property bool $flop
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
        $angle = $this->getRotation();
        if ($angle !== 0) {
            // Need to copy to memory, we have to stay seq.
            $image = $image->copyMemory()->rot('d' . $angle);
        }

        // Flip (mirror about Y axis) if required.
        if ($this->flip) {
            $image = $image->flipver();
        }

        // Flop (mirror about X axis) if required.
        if ($this->flop) {
            $image = $image->fliphor();
        }

        // Remove EXIF Orientation from image, if mirroring is required.
        if ($this->flip || $this->flop) {
            $image->remove(Utils::VIPS_META_ORIENTATION);
        }

        return $image;
    }

    /**
     * Get the angle of rotation.
     *
     * By default, returns zero, i.e. no rotation.
     *
     * @return int The angle of rotation.
     */
    public function getRotation(): int
    {
        // Calculate the angle of rotation
        $missingExifRotation = $this->flip || $this->flop ? $this->exifRotation : 0;
        return (Utils::resolveAngleRotation($this->or) + $missingExifRotation) % 360;
    }
}
