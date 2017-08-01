<?php

namespace AndriesLouw\imagesweserv\Api;

use Jcupitt\Vips\Image;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     *
     * @param  string $url Source URL
     * @param  array $params The manipulation params
     *
     * @return array [
     *      @type Image The image,
     *      @type string The extension of the image,
     *      @type bool Does the image has alpha?
     * ]
     */
    public function run(string $url, array $params): array;
}
