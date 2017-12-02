<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $bri
 */
class Brightness extends BaseManipulator
{
    /**
     * Perform brightness image manipulation.
     *
     * @param Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->bri === null) {
            return $image;
        }

        $brightness = $this->getBrightness();

        if ($brightness !== 0) {
            // Map brightness from -100/100 to -255/255 range
            $brightness *= 2.55;

            // Edit the brightness
            if ($image->hasAlpha()) {
                // Separate alpha channel
                $imageWithoutAlpha = $image->extract_band(0, ['n' => $image->bands - 1]);
                $alpha = $image->extract_band($image->bands - 1, ['n' => 1]);
                $image = $imageWithoutAlpha->linear(
                    [1, 1, 1],
                    [$brightness, $brightness, $brightness]
                )->bandjoin($alpha);
            } else {
                $image = $image->linear([1, 1, 1], [$brightness, $brightness, $brightness]);
            }

            /*$oldInterpretation = $image->interpretation;

            $lch = $image->colourspace(Interpretation::LCH);

            // Edit the brightness
            $image = $lch->add([$brightness, 1, 1])->colourspace($oldInterpretation);*/
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
