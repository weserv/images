<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;

/**
 * @property string $blur
 * @property bool $hasAlpha
 * @property bool $isPremultiplied
 * @property string $accessMethod
 */
class Blur extends BaseManipulator
{
    /**
     * Perform blur image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->blur === null) {
            return $image;
        }

        if ($this->hasAlpha && !$this->isPremultiplied) {
            // Ensures that the image alpha channel is premultiplied before doing any blur transformations
            // to avoid dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply();
            $this->isPremultiplied = true;
        }

        $blur = $this->getBlur();

        if ($blur == -1.0) {
            // Fast, mild blur - averages neighbouring pixels
            $blur = Image::newFromArray(
                [
                    [1.0, 1.0, 1.0],
                    [1.0, 1.0, 1.0],
                    [1.0, 1.0, 1.0]
                ],
                9.0
            );
            $image = $image->conv($blur);
        } else {
            if ($this->accessMethod == Access::SEQUENTIAL) {
                // TODO Figure out how many scanline(s) ('tile_height') it will need.
                $image = $image->linecache([
                    'tile_height' => 10,
                    'access' => Access::SEQUENTIAL,
                    'threaded' => true
                ]);
            }

            $image = $image->gaussblur($blur);
        }

        return $image;
    }

    /**
     * Resolve blur amount.
     *
     * @return float The resolved blur amount.
     */
    public function getBlur(): float
    {
        if (!preg_match('/^[0-9]\.*[0-9]*$/', $this->blur)) {
            return -1.0;
        }

        if ($this->blur < 0.3 || $this->blur > 1000) {
            return -1.0;

        }

        return (float)$this->blur;
    }
}
