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
     *      'image' => *Manipulated image binary data*,
     *      'type' => *The mimetype*,
     *      'extension' => *The extension*
     * ]
     */
    public function run(string $url, string $extension, array $params): array;
}
