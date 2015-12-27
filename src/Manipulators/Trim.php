<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

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
            $image->trim('top-left', null, $trim);
        }

        return $image;
    }

    /**
     * Resolve trim amount.
     * @return string The resolved gamma amount.
     */
    public function getTrim()
    {
        if ($this->trim === 'null') {
            return 10;
        }

        if (!is_numeric($this->trim)) {
            return;
        }

        if ($this->trim < 0 or $this->trim > 100) {
            return;
        }

        return (int)$this->trim;
    }
}
