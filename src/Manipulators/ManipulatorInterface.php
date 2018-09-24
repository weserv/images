<?php

namespace Weserv\Images\Manipulators;

use Jcupitt\Vips\Image;

interface ManipulatorInterface
{
    /**
     * Set the manipulation params.
     *
     * @param mixed[] $params The manipulation params.
     *
     * @return self
     */
    public function setParams(array $params): self;

    /**
     * Get the manipulation params.
     *
     * @return mixed[] The manipulation params.
     */
    public function getParams(): array;

    /**
     * Perform the image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     * @throws \Weserv\Images\Exception\ImageTooLargeException
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image;
}
