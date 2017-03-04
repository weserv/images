<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $crop
 * @property array|null $cropCoordinates
 */
class Crop extends BaseManipulator
{
    /**
     * Perform crop image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $coordinates = $this->cropCoordinates;

        if ($coordinates) {
            $coordinates = $this->limitToImageBoundaries($image, $coordinates);

            $image = $image->crop(
                $coordinates[2],
                $coordinates[3],
                $coordinates[0],
                $coordinates[1]
            );
        }

        return $image;
    }

    /**
     * Limit coordinates to image boundaries.
     *
     * @param  Image $image The source image.
     * @param  array $coordinates The coordinates.
     *
     * @return array The limited coordinates.
     */
    public function limitToImageBoundaries(Image $image, array $coordinates): array
    {
        if ($coordinates[0] > ($image->width - $coordinates[2])) {
            $coordinates[0] = $image->width - $coordinates[2];
        }

        if ($coordinates[1] > ($image->height - $coordinates[3])) {
            $coordinates[1] = $image->height - $coordinates[3];
        }

        return $coordinates;
    }
}
