<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use GuzzleHttp\Exception\RequestException;

class Server
{
    /**
     * Image manipulation API.
     *
     * @var ApiInterface
     */
    protected $api;

    /**
     * Default image manipulations.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Preset image manipulations.
     *
     * @var array
     */
    protected $presets = [];

    /**
     * Create Server instance.
     *
     * @param ApiInterface $api Image manipulation API.
     */
    public function __construct(ApiInterface $api)
    {
        $this->setApi($api);
    }

    /**
     * Get image manipulation API.
     *
     * @return ApiInterface Image manipulation API.
     */
    public function getApi(): ApiInterface
    {
        return $this->api;
    }

    /**
     * Set image manipulation API.
     *
     * @param ApiInterface $api Image manipulation API.
     */
    public function setApi(ApiInterface $api)
    {
        $this->api = $api;
    }

    /**
     * Get default image manipulations.
     *
     * @return array Default image manipulations.
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Set default image manipulations.
     *
     * @param  array $defaults Default image manipulations.
     *
     * @return void
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * Get preset image manipulations.
     *
     * @return array Preset image manipulations.
     */
    public function getPresets()
    {
        return $this->presets;
    }

    /**
     * Set preset image manipulations.
     *
     * @param  array $presets Preset image manipulations.
     * @return void
     */
    public function setPresets(array $presets)
    {
        $this->presets = $presets;
    }

    /**
     * Generate and output image.
     *
     * @param  string $url       Image URL
     * @param  array  $params    Image manipulation params.
     * @param  string $extension Extension of URL
     *
     * @throws ImageTooLargeException if the provided image is too large for processing.
     * @throws RequestException for errors that occur during a transfer or during the on_headers event
     *
     * @return array [
     *      'image' => *Manipulated image binary data*,
     *      'type' => *The mimetype*,
     *      'extension' => *The extension*
     * ]
     */
    public function outputImage(string $url, string $extension, array $params): array
    {
        return $this->api->run($url, $extension, $this->getAllParams($params));
    }

    /**
     * Get all image manipulations params, including defaults and presets.
     *
     * @param  array $params Image manipulation params.
     *
     * @return array All image manipulation params.
     */
    public function getAllParams(array $params): array
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
}
