<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use Jcupitt\Vips\Image;
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
    public function getPresets(): array
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
            $presets = [];
            foreach (explode(',', $params['p']) as $preset) {
                if (isset($this->presets[$preset])) {
                    $presets[] = $this->presets[$preset];
                }
            }

            $all = array_merge($all, ...$presets);
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
     *      @type Image The image,
     *      @type string The extension of the image,
     *      @type bool Does the image has alpha?
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
        list($image, $extension, $hasAlpha) = $this->makeImage($uri->__toString(), $params);

        // Get the allowed image types to convert to
        $allowed = $this->getAllowedImageTypes();

        $needsGif = (isset($params['output']) && $params['output'] === 'gif')
            || (!isset($params['output']) && $extension === 'gif');

        // Check if output is set and allowed
        if (isset($params['output'], $allowed[$params['output']])) {
            $extension = $params['output'];
        } elseif (($hasAlpha && ($extension !== 'png' || $extension !== 'webp')) || !isset($allowed[$extension])) {
            // We force the extension to PNG if:
            //  - The image has alpha and doesn't have the right extension to output alpha.
            //    (useful for shape masking and letterboxing)
            //  - The input extension is not allowed for output.
            $extension = 'png';
        }

        $toBufferOptions = $this->getBufferOptions($params, $extension);

        // Write an image to a formatted string
        $buffer = $image->writeToBuffer(".$extension", $toBufferOptions);

        // Free up memory
        $image = null;

        $mimeType = $allowed[$extension];

        // Check if GD library is installed on the server
        $gdAvailable = extension_loaded('gd') && function_exists('gd_info');

        // If the GD library is installed and a gif output is needed.
        if ($gdAvailable && $needsGif) {
            $buffer = $this->bufferToGif($buffer, $toBufferOptions['interlace'], $hasAlpha);

            // Extension and mime-type are now gif
            $extension = 'gif';
            $mimeType = 'image/gif';
        }

        header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
        header('Cache-Control: max-age=2678400'); //31 days

        if (isset($params['debug']) && $params['debug'] === '1') {
            header('Content-type: text/plain');

            $json = [
                'result' => base64_encode($buffer)
            ];
            echo '[' . date('Y-m-d\TH:i:sP') . '] debug: outputImage ';
            echo json_encode($json) . PHP_EOL;

            // Output buffering is enabled; flush it and turn it off
            ob_end_flush();
        } elseif (isset($params['encoding']) && $params['encoding'] === 'base64') {
            header('Content-type: text/plain');

            echo sprintf('data:%s;base64,%s', $mimeType, base64_encode($buffer));
        } else {
            header("Content-type: $mimeType");

            /*
             * We could ouput the origin filename with this:
             * $friendlyName = pathinfo((new Path($uri->getPath()))->getBasename(), PATHINFO_FILENAME) . $extension;
             * but due to security reasons we've disabled that.
             */
            $friendlyName = "image.$extension";

            if (array_key_exists('download', $params)) {
                header("Content-Disposition: attachment; filename=$friendlyName");
            } else {
                header("Content-Disposition: inline; filename=$friendlyName");
            }

            ob_start();
            echo $buffer;
            header('Content-Length: ' . ob_get_length());
            ob_end_flush();
        }
    }


    /**
     * Get the allowed image types to convert to.
     *
     * Note: It's currently not possible to save gif through libvips
     * See: https://github.com/jcupitt/libvips/issues/235
     * and: https://github.com/jcupitt/libvips/issues/620
     *
     * @return array
     */
    public function getAllowedImageTypes(): array
    {
        return [
            //'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        ];
    }

    /**
     * Resolve quality.
     *
     * @param array $params Parameters array
     *
     * @return int The resolved quality.
     */
    public function getQuality(array $params): int
    {
        $default = 85;

        if (!isset($params['q']) || !is_numeric($params['q'])) {
            return $default;
        }

        if ($params['q'] < 1 || $params['q'] > 100) {
            return $default;
        }

        return (int)$params['q'];
    }

    /**
     * Get the zlib compression level of the lossless PNG output format.
     * The default level is 6.
     *
     * @param array $params Parameters array
     *
     * @return int The resolved zlib compression level.
     */
    public function getCompressionLevel(array $params): int
    {
        $default = 6;

        if (!isset($params['level']) || !is_numeric($params['level'])) {
            return $default;
        }

        if ($params['level'] < 0 || $params['level'] > 9) {
            return $default;
        }

        return (int)$params['level'];
    }

    /**
     * Get the options for a specified extension to pass on to
     * the selected save operation.
     *
     * @param array $params Parameters array
     * @param string $extension Image extension
     *
     * @return array Any options to pass on to the selected
     *     save operation.
     */
    public function getBufferOptions(array $params, string $extension): array
    {
        $toBufferOptions = [];

        if ($extension === 'jpg') {
            // Strip all metadata (EXIF, XMP, IPTC)
            $toBufferOptions['strip'] = true;
            // Set quality (default is 85)
            $toBufferOptions['Q'] = $this->getQuality($params);
            // Use progressive (interlace) scan, if necessary
            $toBufferOptions['interlace'] = array_key_exists('il', $params);
            // Enable libjpeg's Huffman table optimiser
            $toBufferOptions['optimize_coding'] = true;
            return $toBufferOptions;
        }

        if ($extension === 'png') {
            // Use progressive (interlace) scan, if necessary
            $toBufferOptions['interlace'] = array_key_exists('il', $params);
            // zlib compression level (default is 6)
            $toBufferOptions['compression'] = $this->getCompressionLevel($params);
            // Use adaptive row filtering (default is none)
            $toBufferOptions['filter'] = array_key_exists('filter', $params) ? 'all' : 'none';
            return $toBufferOptions;
        }

        if ($extension === 'webp') {
            // Strip all metadata (EXIF, XMP, IPTC)
            $toBufferOptions['strip'] = true;
            // Set quality (default is 85)
            $toBufferOptions['Q'] = $this->getQuality($params);
            // Set quality of alpha layer to 100
            $toBufferOptions['alpha_q'] = 100;
            return $toBufferOptions;
        }

        return $toBufferOptions;
    }

    /**
     * It's currently not possible to save gif through libvips.
     *
     * We don't deprecate GIF output to make sure to not break
     * anyone's apps.
     * If gif output is needed then we are using GD
     * to convert our libvips image to a gif.
     *
     * (Feels a little hackish but there is not an alternative at
     * this moment..)
     *
     * @param string $buffer Old buffer
     * @param bool $interlace Is interlacing needed?
     * @param bool $hasAlpha Does the image has alpha?
     *
     * @return string New GIF buffer
     */
    public function bufferToGif(string $buffer, bool $interlace, bool $hasAlpha): string
    {
        // Create GD image from string (suppress any warnings)
        $gdImage = @imagecreatefromstring($buffer);

        // If image is valid
        if ($gdImage !== false) {
            // Enable interlacing if needed
            if ($interlace) {
                imageinterlace($gdImage, true);
            }

            // Preserve transparency
            if ($hasAlpha) {
                imagecolortransparent($gdImage, imagecolorallocatealpha($gdImage, 0, 0, 0, 127));
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);
            }

            // Turn output buffering on
            ob_start();

            // Output the image to the buffer
            imagegif($gdImage);

            // Read from buffer
            $buffer = ob_get_contents();

            // Delete buffer
            ob_end_clean();

            // Free up memory
            imagedestroy($gdImage);
        }

        return $buffer;
    }
}
