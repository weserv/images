<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;

/**
 * @property string $blur
 * @property bool $isPremultiplied
 * @property string $accessMethod
 */
class Blur extends BaseManipulator
{
    /**
     * Perform blur image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->blur === null) {
            return $image;
        }

        if (!$this->isPremultiplied && $image->hasAlpha()) {
            // Premultiply image alpha channel before blur transformation
            $image = $image->premultiply();
            $this->isPremultiplied = true;
        }

        $blur = $this->getBlur();

        if ($blur === -1.0) {
            // Fast, mild blur - averages neighbouring pixels
            $blur = Image::newFromArray([
                [1.0, 1.0, 1.0],
                [1.0, 1.0, 1.0],
                [1.0, 1.0, 1.0]
            ], 9.0);
            $image = $image->conv($blur);
        } else {
            if ($this->accessMethod === Access::SEQUENTIAL) {
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
        if (!is_numeric($this->blur)) {
            return -1.0;
        }

        if ($this->blur < 0.3 || $this->blur > 1000) {
            return -1.0;
        }

        return (float)$this->blur;
    }
}
