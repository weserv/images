<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Api\ApiInterface;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Exception\RateExceededException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;
use GuzzleHttp\Exception\RequestException;
use Jcupitt\Vips\Config;
use Jcupitt\Vips\DebugLogger;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Server
{
    /**
     * Image manipulation API.
     *
     * @var ApiInterface
     */
    protected $api;

    /**
     * The throttler
     *
     * @var ThrottlerInterface|null
     */
    protected $throttler;

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
     * @param ThrottlerInterface|null $throttler Throttler
     */
    public function __construct(ApiInterface $api, $throttler)
    {
        $this->setApi($api);
        $this->setThrottler($throttler);
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
     *
     * @return void
     */
    public function setApi(ApiInterface $api)
    {
        $this->api = $api;
    }

    /**
     * Get the throttler
     *
     * @return ThrottlerInterface|null Throttler class
     */
    public function getThrottler()
    {
        return $this->throttler;
    }

    /**
     * Set the throttler
     *
     * @param ThrottlerInterface|null $throttler Throttler class
     *
     * @return void
     */
    public function setThrottler($throttler)
    {
        $this->throttler = $throttler;
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
     * @param array $defaults Default image manipulations.
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
    public function getPresets(): array
    {
        return $this->presets;
    }

    /**
     * Set preset image manipulations.
     *
     * @param array $presets Preset image manipulations.
     *
     * @return void
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
     * @param string $url Image URL
     * @param array $params Image manipulation params.
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
    public function makeImage(string $url, array $params): Image
    {
        return $this->api->run($url, $this->getAllParams($params));
    }

    /**
     * Write an image to a formatted string.
     *
     * @param Image $image The image
     * @param array $params Image manipulation params.
     *
     * @throws VipsException for errors that occur during the processing of a Image.
     *
     * @return array [
     * @type   string The formatted image,
     * @type   string Image extension
     * ]
     */
    public function makeBuffer(Image $image, array $params): array
    {
        // Get the operation loader
        $loader = $image->typeof('vips-loader') !== 0 ? $image->get('vips-loader') : 'unknown';

        // Determine image extension from the libvips loader
        $extension = Utils::determineImageExtension($loader);

        // Does this image have an alpha channel?
        $hasAlpha = $image->hasAlpha();

        $needsGif = (isset($params['output']) && $params['output'] === 'gif')
            || (!isset($params['output']) && $extension === 'gif');

        // Check if output is set and allowed
        if (isset($params['output']) && $this->isExtensionAllowed($params['output'])) {
            $extension = $params['output'];
        } elseif (($hasAlpha && ($extension !== 'png' && $extension !== 'webp'))
            || !$this->isExtensionAllowed($extension)) {
            // We force the extension to PNG if:
            //  - The image has alpha and doesn't have the right extension to output alpha.
            //    (useful for shape masking and letterboxing)
            //  - The input extension is not allowed for output.
            $extension = 'png';
        }

        $toBufferOptions = $this->getBufferOptions($params, $extension);

        // Write an image to a formatted string
        $buffer = $image->writeToBuffer(".$extension", $toBufferOptions);

        // Check if GD library is installed on the server
        $gdAvailable = \extension_loaded('gd') && \function_exists('gd_info');

        // If the GD library is installed and a gif output is needed.
        if ($gdAvailable && $needsGif) {
            $buffer = $this->bufferToGif($buffer, $toBufferOptions['interlace'], $hasAlpha);

            // Extension is now gif
            $extension = 'gif';
        }

        return [$buffer, $extension];
    }

    /**
     * Generate and output image.
     *
     * @param string $uri Image URL
     * @param array $params Image manipulation params.
     *
     * @throws RateExceededException if a user rate limit is exceeded
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
     * @return void
     */
    public function outputImage(string $uri, array $params)
    {
        // Throttler can be null
        if ($this->throttler !== null) {
            // For PHPUnit check if REMOTE_ADDR is set
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // Check if rate is exceeded for IP
            if ($this->throttler->isExceeded($ipAddress)) {
                throw new RateExceededException('There are an unusual number of requests coming from this IP address.');
            }
        }

        $isDebug = isset($params['debug']) && $params['debug'] === '1';

        // If debugging is needed
        if ($isDebug) {
            // Turn on output buffering
            ob_start();

            // Set our custom debug logger
            Config::setLogger(new class extends DebugLogger
            {
                public function log($level, $message, array $context = array())
                {
                    // Base64 encode buffers
                    /*if (($message === 'newFromBuffer' || $message === 'findLoadBuffer') &&
                        isset($context['arguments'][0])) {
                        $context['arguments'][0] = base64_encode($context['arguments'][0]);
                    }
                    if ($message === 'writeToBuffer' && isset($context['result'])) {
                        $context['result'] = base64_encode($context['result']);
                    }*/

                    if (($message === 'findLoad' || $message === 'findLoadBuffer' ||
                            $message === 'writeToFile' || $message === 'newFromFile' ||
                            $message === 'newFromBuffer' || $message === 'thumbnail') &&
                        isset($context['arguments'][0])
                    ) {
                        $context['arguments'][0] = '##REDACTED##';
                    }
                    if ($message === 'writeToBuffer' && isset($context['result'])) {
                        $context['result'] = '##REDACTED##';
                    }
                    if ($message === 'thumbnail' && isset($context['arguments'][2]['export_profile'])) {
                        $context['arguments'][2]['export_profile'] = '##REDACTED##';
                    }
                    if ($message === 'thumbnail' && isset($context['arguments'][2]['import_profile'])) {
                        $context['arguments'][2]['import_profile'] = '##REDACTED##';
                    }

                    parent::log($level, $message, $context);
                }
            });
        }

        $image = $this->makeImage($uri, $params);
        list($buffer, $extension) = $this->makeBuffer($image, $params);

        $mimeType = $this->extensionToMimeType($extension);

        header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
        header('Cache-Control: max-age=2678400'); //31 days

        if ($isDebug) {
            header('Content-type: text/plain');

            // Output buffering is enabled; flush it and turn it off
            ob_end_flush();
        } elseif (isset($params['encoding']) && $params['encoding'] === 'base64') {
            header('Content-type: text/plain');

            echo sprintf('data:%s;base64,%s', $mimeType, base64_encode($buffer));
        } else {
            header("Content-type: $mimeType");

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
     * Is the extension allowed to pass on to the selected save operation?
     *
     * Note: It's currently not possible to save gif through libvips
     * See: https://github.com/jcupitt/libvips/issues/235
     * and: https://github.com/jcupitt/libvips/issues/620
     *
     * @param string $extension
     *
     * @return bool
     */
    public function isExtensionAllowed(string $extension): bool
    {
        return $extension === 'jpg' || $extension === 'png' || $extension === 'webp' || $extension === 'tiff';
    }

    /**
     * Determines the appropriate mime type (from list of hardcoded values)
     * using the provided extension.
     *
     * @param string $extension
     *
     * @return string Mime type
     */
    public function extensionToMimeType(string $extension): string
    {
        $mimeTypes = [
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'tiff' => 'image/tiff'
        ];

        return $mimeTypes[$extension];
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
            $toBufferOptions['Q'] = $this->getQuality($params, $extension);
            // Use progressive (interlace) scan, if necessary
            $toBufferOptions['interlace'] = array_key_exists('il', $params);
            // Enable libjpeg's Huffman table optimiser
            $toBufferOptions['optimize_coding'] = true;
        }

        if ($extension === 'png') {
            // Use progressive (interlace) scan, if necessary
            $toBufferOptions['interlace'] = array_key_exists('il', $params);
            // zlib compression level (default is 6)
            $toBufferOptions['compression'] = $this->getQuality($params, $extension);
            // Use adaptive row filtering (default is none)
            $toBufferOptions['filter'] = array_key_exists('filter', $params) ? 'all' : 'none';
        }

        if ($extension === 'webp') {
            // Strip all metadata (EXIF, XMP, IPTC)
            $toBufferOptions['strip'] = true;
            // Set quality (default is 85)
            $toBufferOptions['Q'] = $this->getQuality($params, $extension);
            // Set quality of alpha layer to 100
            $toBufferOptions['alpha_q'] = 100;
        }

        if ($extension === 'tiff') {
            // Strip all metadata (EXIF, XMP, IPTC)
            $toBufferOptions['strip'] = true;
            // Set quality (default is 85)
            $toBufferOptions['Q'] = $this->getQuality($params, $extension);
            // Set the tiff compression
            $toBufferOptions['compression'] = 'jpeg';
        }

        return $toBufferOptions;
    }

    /**
     * Resolve the quality for the provided extension.
     *
     * For a png it returns the zlib compression level
     *
     * @param array $params Parameters array
     * @param string $extension Image extension
     *
     * @return int The resolved quality.
     */
    public function getQuality(array $params, string $extension): int
    {
        $quality = 0;

        if ($extension === 'jpg' || $extension === 'webp' || $extension === 'tiff') {
            $quality = 85;

            if (isset($params['q']) && is_numeric($params['q']) &&
                $params['q'] >= 1 && $params['q'] <= 100) {
                $quality = (int)$params['q'];
            }
        }

        if ($extension === 'png') {
            $quality = 6;

            if (isset($params['level']) && is_numeric($params['level']) &&
                $params['level'] >= 0 && $params['level'] <= 9) {
                $quality = (int)$params['level'];
            }
        }

        return $quality;
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
