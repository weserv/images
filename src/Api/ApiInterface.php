<?php

namespace AndriesLouw\imagesweserv\Api;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     * @param  string $url Source URL
     * @param  array $params The manipulation params.
     * @return string Manipulated image binary data.
     */
    public function run($url, array $params);
}
