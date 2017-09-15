<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interesting;

/**
 * @property string $t
 * @property string $w
 * @property string $h
 * @property string $a
 * @property string $crop
 */
class Crop extends BaseManipulator
{
    /**
     * Perform crop image manipulation.
     *
     * @param Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $width = $this->w;
        $height = $this->h;
        $imageWidth = $image->width;
        $imageHeight = $image->height;

        $isSmartCrop = $this->a === Interesting::ENTROPY || $this->a === Interesting::ATTENTION;
        $isCropNeeded = $this->t === 'square' || $this->t === 'squaredown' || strpos($this->t, 'crop') === 0;

        if (($imageWidth !== $width || $imageHeight !== $height) && $isCropNeeded) {
            $minWidth = min($imageWidth, $width);
            $minHeight = min($imageHeight, $height);

            if ($isSmartCrop) {
                // Need to copy to memory, we have to stay seq.
                $image = $image->copyMemory()->smartcrop($minWidth, $minHeight, ['interesting' => $this->a]);
            } else {
                list($offsetPercentageX, $offsetPercentageY) = $this->getCrop();
                $offsetX = (int)(($imageWidth - $width) * ($offsetPercentageX / 100));
                $offsetY = (int)(($imageHeight - $height) * ($offsetPercentageY / 100));

                list($left, $top) = $this->calculateCrop(
                    [$imageWidth, $imageHeight],
                    [$width, $height],
                    [$offsetX, $offsetY]
                );

                $image = $image->crop($left, $top, $minWidth, $minHeight);
            }
        }

        $coordinates = $this->resolveCropCoordinates($imageWidth, $imageHeight);

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
     * Resolve crop coordinates.
     *
     * @param $imageWidth
     * @param $imageHeight
     *
     * @return array|null The resolved coordinates.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function resolveCropCoordinates($imageWidth, $imageHeight)
    {
        if (!isset($this->crop)) {
            return null;
        }

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
            || ($coordinates[2] >= $imageWidth)
            || ($coordinates[3] >= $imageHeight)
        ) {
            return null;
        }

        return [
            (int)$coordinates[0],
            (int)$coordinates[1],
            (int)$coordinates[2],
            (int)$coordinates[3]
        ];
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
            'bottom-right' => [100, 100]
        ];

        if (isset($cropMethods[$this->a])) {
            return $cropMethods[$this->a];
        }

        // Focal point
        if (strpos($this->a, 'crop-') === 0) {
            $matches = explode('-', substr($this->a, 5));
            if (!isset($matches[2]) && is_numeric($matches[0]) && is_numeric($matches[1])) {
                if ($matches[0] > 100 || $matches[1] > 100) {
                    return [50, 50];
                }

                return [
                    (int)$matches[0],
                    (int)$matches[1]
                ];
            }
        }

        return [50, 50];
    }

    /**
     * Calculate the (left, top) coordinates of the output image
     * within the input image, applying the given x and y offsets.
     *
     * @param array $inCoordinates The image width/height.
     * @param array $outCoordinates The output width/height.
     * @param array $offsets The x/y offset.
     *
     * @return array The crop offset.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function calculateCrop(array $inCoordinates, array $outCoordinates, array $offsets): array
    {
        // Default values
        $left = 0;
        $top = 0;

        // Assign only if valid
        if ($offsets[0] >= 0 && $offsets[0] < ($inCoordinates[0] - $outCoordinates[0])) {
            $left = $offsets[0];
        } elseif ($offsets[0] >= ($inCoordinates[0] - $outCoordinates[0])) {
            $left = $inCoordinates[0] - $outCoordinates[0];
        }

        if ($offsets[1] >= 0 && $offsets[1] < ($inCoordinates[1] - $outCoordinates[1])) {
            $top = $offsets[1];
        } elseif ($offsets[1] >= ($inCoordinates[1] - $outCoordinates[1])) {
            $top = $inCoordinates[1] - $outCoordinates[1];
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
     * @param Image $image The source image.
     * @param array $coordinates The coordinates.
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
