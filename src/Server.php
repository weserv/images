<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use League\Uri\Schemes\Http as HttpUri;

/*use League\Uri\Components\HierarchicalPath as Path;*/

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
     *
     * @return array [
     * @type string Manipulated image binary data,
     * @type string The mimetype of the image,
     * @type string The extension of the image
     * ]
     */
    public function makeImage(string $url, array $params): array
    {
        return $this->api->run($url, $this->getAllParams($params));
    }

    /**
     * Generate and output image.
     *
     * @param HttpUri $uri Image URL
     * @param array $params Image manipulation params.
     */
    public function outputImage(HttpUri $uri, array $params)
    {
        list($image, $type, $extension) = $this->makeImage($uri->__toString(), $params);

        header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
        header('Cache-Control: max-age=2678400'); //31 days

        $isDebug = isset($params['debug']) && $params['debug'] == '1';
        $needsBase64 = isset($params['encoding']) && $params['encoding'] == 'base64';

        // If base64 output is needed or if debugging is enabled
        if ($needsBase64 || $isDebug) {
            header('Content-type: text/plain');

            if ($isDebug) {
                $json = [
                    'result' => base64_encode($image)
                ];
                echo '[' . date('Y-m-d\TH:i:sP') . '] debug: outputImage ';
                echo json_encode($json) . PHP_EOL;
            } else {
                $base64 = sprintf('data:%s;base64,%s', $type, base64_encode($image));
                echo $base64;
            }
        } else {
            header('Content-type: ' . $type);

            /*
             * We could ouput the origin filename with this:
             * $friendlyName = pathinfo((new Path($uri->getPath()))->getBasename(), PATHINFO_FILENAME) . $extension;
             * but due to security reasons we've disabled that.
             */
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
