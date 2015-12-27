<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Intervention\Image\Image;

class Dimension
{
    /**
     * The source image.
     * @var Image
     */
    protected $image;

    /**
     * The device pixel ratio.
     * @var integer
     */
    protected $dpr;

    /**
     * Create dimension helper instance.
     * @param Image $image The source image.
     * @param integer $dpr The device pixel ratio.
     */
    public function __construct(Image $image, $dpr = 1)
    {
        $this->image = $image;
        $this->dpr = $dpr;
    }

    /**
     * Resolve the dimension.
     * @param  string $value The dimension value.
     * @return double The resolved dimension.
     */
    public function get($value)
    {
        if (is_numeric($value) and $value > 0) {
            return (double)$value * $this->dpr;
        }
        if (preg_match('/^(\d{1,2}(?!\d)|100)(w|h)$/', $value, $matches)) {
            if ($matches[2] === 'h') {
                return (double)$this->image->height() * ($matches[1] / 100);
            }
            return (double)$this->image->width() * ($matches[1] / 100);
        }
    }
}
