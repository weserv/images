<?php

namespace AndriesLouw\imagesweserv\Api;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     * @param  string $url Source URL
     * @param  string $extension Extension of URL
     * @param  array $params The manipulation params.
     * @return string Manipulated image binary data.
     */
    public function run($url, $extension, array $params);
}
