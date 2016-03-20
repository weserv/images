<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

/**
 * @property string $output
 * @property string $q
 */
class Encode extends BaseManipulator
{
    /**
     * Perform output image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $format = $this->getFormat($image);
        $quality = $this->getQuality();

        if ($format === 'jpg' || $format === 'gif') {
            $image = $image->getDriver()
                ->newImage($image->width(), $image->height(), '#fff')
                ->insert($image, 'top-left', 0, 0);
        }

        if ($this->il) {
            $image->interlace();
        }

        // Memory leak, see: https://github.com/Intervention/image/issues/426
        return $image->encode($format, $quality);
    }

    /**
     * Resolve format.
     * @param  Image $image The source image.
     * @return string The resolved format.
     */
    public function getFormat(Image $image)
    {
        $allowed = [
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        if (array_key_exists($this->output, $allowed)) {
            return $this->output;
        }

        if ($format = array_search($image->mime(), $allowed, true)) {
            return $format;
        }

        return 'jpg';
    }

    /**
     * Resolve quality.
     * @return string The resolved quality.
     */
    public function getQuality()
    {
        $default = 85;

        if (!is_numeric($this->q)) {
            return $default;
        }

        if ($this->q < 0 or $this->q > 100) {
            return $default;
        }

        return (int)$this->q;
    }

}
