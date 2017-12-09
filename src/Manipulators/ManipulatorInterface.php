<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;

interface ManipulatorInterface
{
    /**
     * Set the manipulation params.
     *
     * @param array $params The manipulation params.
     *
     * @return self
     */
    public function setParams(array $params);

    /**
     * Get the manipulation params.
     *
     * @return array The manipulation params.
     */
    public function getParams(): array;

    /**
     * Perform the image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     * @throws \AndriesLouw\imagesweserv\Exception\ImageTooLargeException
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image;
}
