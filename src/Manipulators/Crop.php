<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $t
 * @property string $h
 * @property string $w
 * @property string $a
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
        $cropArr = ['square' => 0, 'squaredown' => 1, 'crop' => 2];

        $width = $this->w;
        $height = $this->h;
        $imageWidth = $image->width;
        $imageHeight = $image->height;

        if ($coordinates) {
            $coordinates = $this->limitToImageBoundaries($image, $coordinates);

            $image = $image->crop(
                $coordinates[2],
                $coordinates[3],
                $coordinates[0],
                $coordinates[1]
            );
        } elseif (($imageWidth !== $width || $imageHeight !== $height) && (isset($cropArr[$this->t]) || substr($this->t, 0, 4) === 'crop')) {
            $minWidth = min($imageWidth, $width);
            $minHeight = min($imageHeight, $height);

            if ($this->a === 'entropy' || $this->a === 'attention') {
                $image = $image->smartcrop($minWidth, $minHeight, ['interesting' => $this->a]);
            } else {
                list($offsetPercentageX, $offsetPercentageY) = $this->getCrop();
                $offsetX = (int)(($imageWidth * $offsetPercentageX / 100) - ($width / 2));
                $offsetY = (int)(($imageHeight * $offsetPercentageY / 100) - ($height / 2));

                list($left, $top) = $this->calculateCrop($imageWidth, $imageHeight, $width, $height,
                    $offsetX, $offsetY);

                $image = $image->crop($left, $top, $minWidth, $minHeight);
            }
        }

        return $image;
    }

    /**
     * Resolve crop.
     *
     * @return array The resolved crop.
     */
    public function getCrop(): array
    {
        $cropMethods = [
            'top-left' => [0, 0],
            't' => [50, 0], // Deprecated use top instead
            'top' => [50, 0],
            'top-right' => [100, 0],
            'l' => [0, 50], // Deprecated use left instead
            'left' => [0, 50],
            'center' => [50, 50],
            'r' => [0, 50], // Deprecated use right instead
            'right' => [100, 50],
            'bottom-left' => [0, 100],
            'b' => [50, 100], // Deprecated use bottom instead
            'bottom' => [50, 100],
            'bottom-right' => [100, 100],
        ];

        if (isset($cropMethods[$this->a])) {
            return $cropMethods[$this->a];
        }

        $matches = explode('-', $this->a);

        if (isset($matches[0]) && $matches[0] === 'crop' && isset($matches[1]) && isset($matches[2]) && !isset($matches[3])
            && is_numeric($matches[1]) && is_numeric($matches[2])
        ) {
            if ($matches[1] > 100 || $matches[2] > 100) {
                return [50, 50];
            }

            return [
                (int)$matches[1],
                (int)$matches[2],
            ];
        }

        return [50, 50];
    }

    /**
     * Calculate the (left, top) coordinates of the output image
     * within the input image, applying the given x and y offsets.
     *
     * @param int $inWidth The image width.
     * @param int $inHeight The image height.
     * @param int $outWidth The output width.
     * @param int $outHeight The output height.
     * @param int $x The x offset.
     * @param int $y The y offset.
     *
     * @return array The crop offset.
     */
    public function calculateCrop(int $inWidth, int $inHeight, int $outWidth, int $outHeight, int $x, int $y): array
    {
        // Default values
        $left = 0;
        $top = 0;

        // Assign only if valid
        if ($x >= 0 && $x < ($inWidth - $outWidth)) {
            $left = $x;
        } elseif ($x >= ($inWidth - $outWidth)) {
            $left = $inWidth - $outWidth;
        }

        if ($y >= 0 && $y < ($inHeight - $outHeight)) {
            $top = $y;
        } elseif ($y >= ($inHeight - $outHeight)) {
            $top = $inHeight - $outHeight;
        }

        // The resulting left and top could have been outside the image after calculation from bottom/right edges
        if ($left < 0) {
            $left = 0;
        }

        if ($top < 0) {
            $top = 0;
        }

        return [$left, $top];
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
