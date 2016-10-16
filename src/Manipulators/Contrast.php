<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
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
            $contrast = Utils::mapToRange($contrast, -100, 100, -30, 30);

            $increase = $contrast > 0;

            $max = $this->maxAlpha;

            $abs = abs($contrast);

            $toneLUT = Image::tonelut([
                'in_max' => $max,
                'out_max' => $max,
                'Ps' => 0,
                'Pm' => 0.5,
                'Ph' => 1,
                'S' => $increase ? -$abs : $abs,
                'M' => 0,
                'H' => $increase ? $abs : -$abs,
            ]);

            $image = $image->maplut($toneLUT);
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
