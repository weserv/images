<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use GuzzleHttp\Exception\RequestException;
use ImagickException;
use Intervention\Image\Exception\NotReadableException;

class Server
{
    /**
     * Image manipulation API.
     * @var ApiInterface
     */
    protected $api;

    /**
     * Default image manipulations.
     * @var array
     */
    protected $defaults = [];

    /**
     * Preset image manipulations.
     * @var array
     */
    protected $presets = [];

    /**
     * Create Server instance.
     * @param ApiInterface $api Image manipulation API.
     */
    public function __construct(ApiInterface $api)
    {
        $this->setApi($api);
    }

    /**
     * Get image manipulation API.
     * @return ApiInterface Image manipulation API.
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     * Set image manipulation API.
     * @param ApiInterface $api Image manipulation API.
     */
    public function setApi(ApiInterface $api)
    {
        $this->api = $api;
    }

    /**
     * Get default image manipulations.
     * @return array Default image manipulations.
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * Set default image manipulations.
     * @param array $defaults Default image manipulations.
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * Get preset image manipulations.
     * @return array Preset image manipulations.
     */
    public function getPresets()
    {
        return $this->presets;
    }

    /**
     * Set preset image manipulations.
     * @param array $defaults Preset image manipulations.
     */
    public function setPresets(array $presets)
    {
        $this->presets = $presets;
    }

    /**
     * Generate and output image.
     * @param  string $url Image URL
     * @param  array $params Image manipulation params.
     * @throws NotReadableException if the provided file can not be read
     * @throws ImageTooLargeException if the provided image is too large for processing.
     * @throws RequestException for errors that occur during a transfer or during the on_headers event
     * @throws ImagickException for errors that occur during image manipulation
     * @return string Manipulated image binary data.
     */
    public function outputImage($url, array $params)
    {
        return $this->api->run($url, $this->getAllParams($params));
    }

    /**
     * Get all image manipulations params, including defaults and presets.
     * @param  array $params Image manipulation params.
     * @return array All image manipulation params.
     */
    public function getAllParams(array $params)
    {
        $all = $this->defaults;

        if (isset($params['p'])) {
            foreach (explode(',', $params['p']) as $preset) {
                if (isset($this->presets[$preset])) {
                    $all = array_merge($all, $this->presets[$preset]);
                }
            }
        }

        return array_merge($all, $params);
    }

    /**
     * Get the current mime type after encoding
     * we cannot use directly $image->mime() because
     * that is the initial mime type of the image (so before any encoding)
     * See: https://github.com/Intervention/image/issues/471
     *
     * @param  string $mimeType the mime which the user wants to format to
     * @param  string $mimeTypeImage the initial mime type before encoding
     * @param  array $allowed allowed extensions
     * @return string
     */
    public function getCurrentMimeType($mimeType, $mimeTypeImage, $allowed = null)
    {
        if (is_null($allowed)) {
            $allowed = [
                'gif' => 'image/gif',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
            ];
        }

        if (array_key_exists($mimeType, $allowed)) {
            return $allowed[$mimeType];
        }

        if ($format = array_search($mimeTypeImage, $allowed, true)) {
            return $allowed[$format];
        }

        return 'image/jpeg';
    }
}