<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $crop
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
        $coordinates = $this->getCoordinates($image);

        if ($coordinates !== null) {
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
     * Resolve coordinates.
     *
     * @param  Image $image The source image.
     *
     * @return array|null The resolved coordinates.
     */
    public function getCoordinates(Image $image)
    {
        $coordinates = explode(',', $this->crop);

        if (count($coordinates) !== 4
            || (!is_numeric($coordinates[0]))
            || (!is_numeric($coordinates[1]))
            || (!is_numeric($coordinates[2]))
            || (!is_numeric($coordinates[3]))
            || ($coordinates[0] <= 0)
            || ($coordinates[1] <= 0)
            || ($coordinates[2] < 0)
            || ($coordinates[3] < 0)
            || ($coordinates[2] >= $image->width)
            || ($coordinates[3] >= $image->height)
        ) {
            return null;
        }

        return [
            (int)$coordinates[0],
            (int)$coordinates[1],
            (int)$coordinates[2],
            (int)$coordinates[3],
        ];
    }

    /**
     * Limit coordinates to image boundaries.
     *
     * @param  Image $image       The source image.
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
