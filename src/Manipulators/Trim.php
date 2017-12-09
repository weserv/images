<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string|bool $trim
 */
class Trim extends BaseManipulator
{
    /**
     * Perform trim image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        // Make sure that trimming is required
        if (!isset($this->trim) || $image->width < 3 || $image->height < 3) {
            $this->trim = false;
            return $image;
        }

        $trim = $this->getTrim();

        $image = $this->getTrimmedImage($image, $trim);

        return $image;
    }

    /**
     * Resolve trim amount.
     *
     * @return int The resolved trim amount.
     */
    public function getTrim(): int
    {
        $default = 10;

        if (!is_numeric($this->trim)) {
            return $default;
        }

        if ($this->trim < 1 || $this->trim > 254) {
            return $default;
        }

        return (int)$this->trim;
    }

    /**
     * Perform trim image manipulation.
     *
     * @param Image $image The source image.
     * @param int $sensitivity Trim sensitivity
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function getTrimmedImage(Image $image, int $sensitivity): Image
    {
        // Find the value of the pixel at (0, 0), `find_trim` search for all pixels
        // significantly different from this
        if ($image->hasAlpha()) {
            // If the image has alpha, we'll need to flatten before `getpoint`
            // to get a correct background value.
            $background = $image->flatten()->getpoint(0, 0);
        } else {
            $background = $image->getpoint(0, 0);
        }

        // Scale up 8-bit values to match 16-bit input image
        $multiplier = Utils::is16Bit($image->interpretation) ? 256 : 1;

        // Background / object threshold
        $threshold = $sensitivity * $multiplier;

        // Search for the bounding box of the non-background area
        $trim = $image->find_trim([
            'threshold' => $threshold,
            'background' => $background
        ]);

        if ($trim['width'] === 0 || $trim['height'] === 0) {
            trigger_error(
                sprintf('Unexpected error while trimming. Sensitivity (%s) is too high.', $sensitivity),
                E_USER_WARNING
            );
            return $image;
        }

        // And now crop the original image
        return $image->crop($trim['left'], $trim['top'], $trim['width'], $trim['height']);
    }
}
