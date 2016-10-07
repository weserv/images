<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $con
 */
class Contrast extends BaseManipulator
{
    /**
     * Perform contrast image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $contrast = $this->getContrast();

        if ($contrast !== 0) {
            /*$colourSpaceBeforeContrast = Utils::interpretationToColourSpace($image->interpretation);

            $sharpen = $contrast > 0;
            $contrast /= 4;*/

            /**
             * TODO: Find an alternative for this imagick function in php-vips:
             * Imagick::sigmoidalContrastImage($sharpen, $contrast, 0);
             *
             * References:
             * - http://www.imagemagick.org/Usage/color_mods/#sigmoidal
             * - http://php.net/manual/en/imagick.sigmoidalcontrastimage.php
             */
        }

        return $image;
    }

    /**
     * Resolve contrast amount.
     *
     * @return int The resolved contrast amount.
     */
    public function getContrast(): int
    {
        if (!preg_match('/^-*[0-9]+$/', $this->con)) {
            return 0;
        }

        if ($this->con < -100 || $this->con > 100) {
            return 0;
        }

        return (int)$this->con;
    }
}
