<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;

/**
 * @property string $trim
 * @property bool $is16Bit
 */
class Trim extends BaseManipulator
{
    /**
     * Perform trim image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if (!$this->trim) {
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

        if ($this->trim < 0 || $this->trim > 255) {
            return $default;
        }

        return (int)$this->trim;
    }

    /**
     * Perform trim image manipulation.
     *
     * @param  Image $image The source image.
     * @param  int $sensitivity Trim sensitivity
     *
     * @throws VipsException for errors that occur during the processing of a Image
     *
     * @return Image The manipulated image.
     */
    public function getTrimmedImage(Image $image, $sensitivity): Image
    {
        $background = $image->getpoint(0, 0);

        // Scale up 8-bit values to match 16-bit input image
        $multiplier = $this->is16Bit ? 256 : 1;

        // we need to smooth the image, subtract the background from every pixel, take
        // the absolute value of the difference, then threshold
        $mask = $image->median(3)->subtract($background)->abs()->more($sensitivity * $multiplier);

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
            throw new VipsException('Unexpected error while trimming. Try to lower the sensitivity.');
        }

        // and now crop the original image
        return $image->crop($left, $top, $width, $height);
    }
}
