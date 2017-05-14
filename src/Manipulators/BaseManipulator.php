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
     *
     * @return self
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Get the manipulation params.
     *
     * @return array The manipulation params.
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get a specific manipulation param.
     *
     * @param  string $name The manipulation name.
     *
     * @return string|null The manipulation value.
     */
    public function __get(string $name)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }
        return null;
    }

    /**
     * Whether or not an item exists by key
     *
     * @param   string $key
     * @return  bool
     */
    public function __isset(string $key)
    {
        return isset($this->params[$key]) || array_key_exists($key, $this->params);
    }

    /**
     * Set any property on the manipulation params.
     *
     * @param string $name The property name.
     * @param mixed $value The value to set for this property.
     */
    public function __set(string $name, $value)
    {
        $this->params[$name] = $value;
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
