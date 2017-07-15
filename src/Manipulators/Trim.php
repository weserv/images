<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

/**
 * @property string $trim
 * @property bool $hasAlpha
 * @property bool $isPremultiplied
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
        // Make sure that trimming is required
        if (!isset($this->trim) || $image->width < 3 || $image->height < 3) {
            $this->trim = false;
            return $image;
        }

        if ($this->hasAlpha && !$this->isPremultiplied) {
            // Premultiply image alpha channel before trim transformation to avoid
            // dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply();
            $this->isPremultiplied = true;
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
     * @param  Image $image The source image.
     * @param  int $sensitivity Trim sensitivity
     *
     * @return Image The manipulated image.
     */
    public function getTrimmedImage(Image $image, int $sensitivity): Image
    {
        // find the value of the pixel at (0, 0) ... we will search for all pixels
        // significantly different from this
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
            trigger_error(sprintf('Unexpected error while trimming. Sensitivity (%s) is too high.', $sensitivity),
                E_USER_WARNING);
            return $image;
        }

        // and now crop the original image
        return $image->crop($left, $top, $width, $height);
    }
}
