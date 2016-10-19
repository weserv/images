<?php

namespace AndriesLouw\imagesweserv\Api;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     *
     * @param  string $url Source URL
     * @param  string $extension Extension of URL
     * @param  array $params The manipulation params
     *
     * @return array [
     * @type string Manipulated image binary data,
     * @type string The mimetype of the image,
     * @type string The extension of the image
     * ]
     */
    public function run(string $url, string $extension, array $params): array;
}
