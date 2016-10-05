<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

abstract class BaseManipulator implements ManipulatorInterface
{
    /**
     * The manipulation params.
     *
     * @var array
     */
    public $params = [];

    /**
     * Set the manipulation params.
     *
     * @param array $params The manipulation params.
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Get a specific manipulation param.
     *
     * @param  string $name The manipulation name.
     *
     * @return string The manipulation value.
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->params)) {
            return ($this->params[$name] != null) ? $this->params[$name] : '';
        }
    }

    /**
     * Perform the image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    abstract public function run(Image $image): Image;
}
