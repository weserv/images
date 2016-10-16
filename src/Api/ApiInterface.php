<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use GuzzleHttp\Exception\RequestException;
use Jcupitt\Vips\Exception as VipsException;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     *
     * @param  string $url Source URL
     * @param  string $extension Extension of URL
     * @param  array $params The manipulation params
     *
     * @throws ImageNotReadableException if the provided image is not readable.
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws RequestException for errors that occur during a transfer or during
     *      the on_headers event
     * @throws VipsException for errors that occur during the processing of a Image
     *
     * @return array [
     *      'image' => *Manipulated image binary data*,
     *      'type' => *The mimetype*,
     *      'extension' => *The extension*
     * ]
     */
    public function run(string $url, string $extension, array $params): array;
}
