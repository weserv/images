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
     * @return Image The image
     */
    public function run(string $url, array $params): Image;
}
