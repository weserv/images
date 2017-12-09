<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use GuzzleHttp\Exception\RequestException;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;

interface ApiInterface
{
    /**
     * Perform image manipulations.
     *
     * @param string $url Source URL
     * @param array $params The manipulation params
     *
     * @throws ImageNotReadableException if the provided image is not readable.
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws VipsException for errors that occur during the processing of a Image.
     * @throws ImageNotValidException if the requested image is not a valid
     *      image.
     * @throws ImageTooBigException if the requested image is too big to be
     *      downloaded.
     * @throws RequestException for errors that occur during a transfer
     *      or during the on_headers event.
     * @throws \InvalidArgumentException if the redirect URI can not be
     *      parsed (with parse_url).
     *
     * @return Image The image
     */
    public function run(string $url, array $params): Image;
}
