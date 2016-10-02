<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $trim
 */
class Trim extends BaseManipulator
{
    /**
     * Perform trim image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $trim = $this->getTrim();

        if ($trim) {
            $image = $this->getTrimmedImage($image, $trim);
        }

        return $image;
    }

    /**
     * Resolve trim amount.
     * @return string The resolved trim amount.
     */
    public function getTrim()
    {
        if ($this->trim === '') {
            return 10;
        }

        if (!is_numeric($this->trim)) {
            return;
        }

        if ($this->trim < 0 || $this->trim > 100) {
            return;
        }

        return (int)$this->trim;
    }

    /**
     * Perform trim image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function getTrimmedImage($image, $tolerance)
    {
        $background = $image->getpoint(0, 0);

        $max = Utils::maximumImageAlpha($image->interpretation);

        // we need to smooth the image, subtract the background from every pixel, take
        // the absolute value of the difference, then threshold
        $mask = $image->median(3)->subtract($background)->abs()->more($max * $tolerance / 100);

        // sum mask rows and columns, then search for the first non-zero sum in each
        // direction
        $project = $mask->project();

        $profileLeft = $project['columns']->profile();
        $profileRight = $project['columns']->fliphor()->profile();
        $profileTop = $project['rows']->profile();
        $profileBottom = $project['rows']->flipver()->profile();

        $left = (int)floor($profileLeft['rows']->min());
        $right = $project['columns']->width - (int)floor($profileRight['rows']->min());
        $top = (int)floor($profileTop['columns']->min());
        $bottom = $project['rows']->height - (int)floor($profileBottom['columns']->min());

        $width = $right - $left;
        $height = $bottom - $top;

        if ($width <= 0 || $height <= 0) {
            throw new \RuntimeException("Unexpected error while trimming. Try to lower the tolerance");
        }

        // and now crop the original image
        return $image->crop($left, $top, $width, $height);
    }
}
