<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $bri
 * @property bool $hasAlpha
 */
class Brightness extends BaseManipulator
{
    /**
     * Perform brightness image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if (!$this->bri) {
            return $image;
        }

        $amount = $this->getBrightness();

        if ($amount !== 0) {
            $amount = Utils::mapToRange($amount, -100, 100, -255, 255);

            // Edit the brightness
            if ($this->hasAlpha) {
                // Separate alpha channel
                $imageWithoutAlpha = $image->extract_band(0, ['n' => $image->bands - 1]);
                $alpha = $image->extract_band($image->bands - 1, ['n' => 1]);
                $image = $imageWithoutAlpha->linear([1, 1, 1], [$amount, $amount, $amount])->bandjoin($alpha);
            } else {
                $image = $image->linear([1, 1, 1], [$amount, $amount, $amount]);
            }

            /*$oldInterpretation = $image->interpretation;

            $lch = $image->colourspace(Interpretation::LCH);

            // Edit the brightness
            $image = $lch->add([$amount, 1, 1])->colourspace($oldInterpretation);*/
        }

        return $image;
    }

    /**
     * Resolve brightness amount.
     *
     * @return int The resolved brightness amount.
     */
    public function getBrightness(): int
    {
        if (!is_numeric($this->bri)) {
            return 0;
        }

        if ($this->bri < -100 || $this->bri > 100) {
            return 0;
        }

        return (int)$this->bri;
    }
}
