<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use League\Uri\Schemes\Http as HttpUri;

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
     * @param array $presets Preset image manipulations.
     */
    public function setPresets(array $presets)
    {
        $this->presets = $presets;
    }

    /**
     * Get all image manipulations params, including defaults and presets.
     *
     * @param array $params Image manipulation params.
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

    /**
     * Generate manipulated image.
     *
     * @param  string $url Image URL
     * @param  array $params Image manipulation params.
     * @param  string $extension Extension of URL
     *
     * @return array [
     * @type string Manipulated image binary data,
     * @type string The mimetype of the image,
     * @type string The extension of the image
     * ]
     */
    public function makeImage(string $url, string $extension, array $params): array
    {
        return $this->api->run($url, $extension, $this->getAllParams($params));
    }

    /**
     * Generate and output image.
     *
     * @param HttpUri $uri Image URL
     * @param array $params Image manipulation params.
     * @param string $extension Extension of URL
     */
    public function outputImage(HttpUri $uri, string $extension, array $params)
    {
        list($image, $type, $extension) = $this->makeImage($uri->__toString(), $extension, $params);

        header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
        header('Cache-Control: public, max-age=2678400'); //31 days

        if (isset($params['encoding']) && $params['encoding'] == 'base64') {
            $base64 = sprintf('data:%s;base64,%s', $type, base64_encode($image));

            header('Content-type: text/plain');

            echo $base64;
        } else {
            header('Content-type: ' . $type);

            /* pathinfo($uri->path->getBasename(), PATHINFO_FILENAME) */
            $friendlyName = 'image.' . $extension;

            if (array_key_exists('download', $params)) {
                header('Content-Disposition: attachment; filename=' . $friendlyName);
            } else {
                header('Content-Disposition: inline; filename=' . $friendlyName);
            }

            ob_start();
            echo $image;
            header('Content-Length: ' . ob_get_length());
            ob_end_flush();
        }
    }
}
