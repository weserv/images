<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;

interface ManipulatorInterface
{
    /**
     * Set the manipulation params.
     *
     * @param array $params The manipulation params.
     */
    public function setParams(array $params);

    /**
     * Perform the image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws VipsException for errors that occur during the processing of a Image
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image;
}
