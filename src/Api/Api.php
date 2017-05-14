<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Exception\RateExceededException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Config;
use Jcupitt\Vips\DebugLogger;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;

class Api implements ApiInterface
{
    /**
     * Collection of manipulators.
     *
     * @var array
     */
    protected $manipulators;

    /**
     * The PHP HTTP client
     *
     * @var Client
     */
    protected $client;

    /**
     * The throttler
     *
     * @var ThrottlerInterface|null
     */
    protected $throttler;

    /**
     * The current mime type
     *
     * @var Client
     */
    protected $mimeType;

    /**
     * Create API instance.
     *
     * @param Client $client The Guzzle
     * @param ThrottlerInterface|null $throttler Throttler
     * @param array $manipulators Collection of manipulators.
     */
    public function __construct(Client $client, $throttler, array $manipulators)
    {
        $this->setClient($client);
        $this->setThrottler($throttler);
        $this->setManipulators($manipulators);
    }

    /**
     * Get the PHP HTTP client
     *
     * @return Client The Guzzle client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the PHP HTTP client
     *
     * @param Client $client Guzzle client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
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
     */
    public function setThrottler($throttler)
    {
        $this->throttler = $throttler;
    }

    /**
     * Get the manipulators.
     *
     * @return array Collection of manipulators.
     */
    public function getManipulators(): array
    {
        return $this->manipulators;
    }

    /**
     * Set the manipulators.
     *
     * @param array $manipulators Collection of manipulators.
     *
     * @throws InvalidArgumentException if there's a manipulator which not extends
     *      ManipulatorInterface
     */
    public function setManipulators(array $manipulators)
    {
        foreach ($manipulators as $manipulator) {
            if (!($manipulator instanceof ManipulatorInterface)) {
                throw new InvalidArgumentException('Not a valid manipulator.');
            }
        }

        $this->manipulators = $manipulators;
    }

    /**
     * Perform image manipulations.
     *
     * @param  string $url Source URL
     * @param  array $params The manipulation params
     *
     * @throws RateExceededException if a user rate limit is exceeded
     * @throws ImageNotReadableException if the provided image is not readable.
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws RequestException for errors that occur during a transfer or during
     *      the on_headers event
     * @throws VipsException for errors that occur during the processing of a Image
     *
     * @return array [
     * @type string Manipulated image binary data,
     * @type string The mimetype of the image,
     * @type string The extension of the image
     * ]
     */
    public function run(string $url, array $params): array
    {
        // Throttler can be null
        if ($this->throttler) {
            // For PHPUnit check if REMOTE_ADDR is set
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

            // Check if rate is exceeded for IP
            if ($this->throttler->isExceeded($ip)) {
                throw new RateExceededException();
            }
        }

        // libvips caching is not needed
        Config::cacheSetMax(0);
        Config::cacheSetMaxFiles(0);
        Config::cacheSetMaxMem(0);

        // If debugging is needed
        if (isset($params['debug']) && $params['debug'] === '1') {
            // Turn on output buffering
            ob_start();

            // Set our php-vips debug logger
            Config::setLogger(new DebugLogger());
        }

        $tmpFileName = $this->client->get($url);

        // Things that won't work with sequential mode images:
        //  - Trim (will scan the whole image once to find the crop area).
        //  - A 90/180/270-degree rotate (will need to read a column of pixels for every output line it writes).
        //  - Smart crop
        $isTrim = isset($params['trim']) || array_key_exists('trim', $params);
        $isSmartCrop = isset($params['a']) && ($params['a'] === 'entropy' || $params['a'] === 'attention');

        // If any of the above adjustments; don't use sequential mode read.
        $params['accessMethod'] = $isTrim || $isSmartCrop ? Access::RANDOM : Access::SEQUENTIAL;

        // Save our temporary file name
        $params['tmpFileName'] = $tmpFileName;

        $loadOptions = [
            'access' => $params['accessMethod']
        ];

        // Find the name of the load operation vips will use to load a file
        $params['loader'] = Image::findLoad($tmpFileName);

        // In order to pass the page property to the correct loader
        // we check if the loader permits a page property.
        if (isset($params['page']) && is_numeric($params['page']) &&
            $params['page'] >= 0 && $params['page'] <= 100000 &&
            ($params['loader'] === 'VipsForeignLoadPdfFile' ||
                $params['loader'] === 'VipsForeignLoadTiffFile' ||
                $params['loader'] === 'VipsForeignLoadMagickFile')
        ) {
            $loadOptions['page'] = (int)$params['page'];
        }

        try {
            // Create a new Image instance from our temporary file
            $image = Image::newFromFile($tmpFileName, $loadOptions);
        } catch (VipsException $e) {
            // Keep throwing it (with a wrapper).
            throw new ImageNotReadableException('Image not readable. Is it a valid image?', 0, $e);
        }

        // Determine image extension from the libvips loader
        $extension = Utils::determineImageExtension($params['loader']);

        // Get the allowed image types to convert to
        $allowed = $this->getAllowedImageTypes();

        // Put common variables in the parameters
        $params['hasAlpha'] = $image->hasAlpha();
        $params['is16Bit'] = Utils::is16Bit($image->interpretation);
        $params['isPremultiplied'] = false;

        // Calculate angle of rotation
        list($params['rotation'], $params['flip'], $params['flop']) = Utils::calculateRotationAndFlip($params, $image);

        // A 90/180/270-degree rotate doesn't work with sequential access.
        // If our access method is sequential and rotation is needed;
        // Force the access method to random and reload our image.
        if ($params['accessMethod'] === Access::SEQUENTIAL && $params['rotation'] !== 0) {
            $params['accessMethod'] = Access::RANDOM;
            $loadOptions['access'] = Access::RANDOM;
            // Reload image
            $image = Image::newFromFile($tmpFileName, $loadOptions);
        }

        // Set width and height to zero if it's invalid
        // Otherwise cast it to a integer
        if (!isset($params['w']) || !is_numeric($params['w']) || $params['w'] <= 0) {
            $params['w'] = 0;
        } else {
            $params['w'] = (int)$params['w'];
        }

        if (!isset($params['h']) || !is_numeric($params['h']) || $params['h'] <= 0) {
            $params['h'] = 0;
        } else {
            $params['h'] = (int)$params['h'];
        }

        // Do our image manipulations
        foreach ($this->manipulators as $manipulator) {
            $manipulator->setParams($params);

            $image = $manipulator->run($image);

            // A manipulator can override the given parameters
            $params = $manipulator->getParams();
        }

        // Reverse premultiplication after all transformations:
        if ($params['isPremultiplied']) {
            $image = $image->unpremultiply();

            // Cast pixel values to integer
            if ($params['is16Bit']) {
                $image = $image->cast(BandFormat::USHORT);
            } else {
                $image = $image->cast(BandFormat::UCHAR);
            }
        }

        $needsGif = (isset($params['output']) && $params['output'] === 'gif')
            || (!isset($params['output']) && $extension === 'gif');

        // Check if output is set and allowed
        if (isset($params['output']) && isset($allowed[$params['output']])) {
            $extension = $params['output'];
        } else {
            $supportsAlpha = ['png', 'webp'];
            if (($params['hasAlpha'] && !isset($supportsAlpha[$extension])) || !isset($allowed[$extension])) {
                // We force the extension to PNG if:
                //  - The image has alpha and doesn't have the right extension to output alpha.
                //    (useful for shape masking and letterboxing)
                //  - The input extension is not allowed for output.
                $extension = 'png';
            }
        }

        $toBufferOptions = [];

        switch ($extension) {
            case 'jpg':
                // Strip all metadata (EXIF, XMP, IPTC)
                $toBufferOptions['strip'] = true;
                // Set quality (default is 85)
                $toBufferOptions['Q'] = $this->getQuality($params);
                // Use progressive (interlace) scan, if necessary
                $toBufferOptions['interlace'] = array_key_exists('il', $params);
                // Enable libjpeg's Huffman table optimiser
                $toBufferOptions['optimize_coding'] = true;
                break;
            case 'png':
                // Use progressive (interlace) scan, if necessary
                $toBufferOptions['interlace'] = array_key_exists('il', $params);
                // zlib compression level (default is 6)
                $toBufferOptions['compression'] = $this->getCompressionLevel($params);
                // Use adaptive row filtering (default is none)
                $toBufferOptions['filter'] = array_key_exists('filter', $params) ? 'all' : 'none';
                break;
            case 'webp':
                // Strip all metadata (EXIF, XMP, IPTC)
                $toBufferOptions['strip'] = true;
                // Set quality (default is 85)
                $toBufferOptions['Q'] = $this->getQuality($params);
                // Set quality of alpha layer to 100
                $toBufferOptions['alpha_q'] = 100;
                break;
        }

        // Write an image to a formatted string
        $buffer = $image->writeToBuffer(".$extension", $toBufferOptions);

        // Free up memory
        $image = null;

        $mimeType = $allowed[$extension];

        /*
         * Note:
         * It's currently not possible to save gif through libvips.
         *
         * We don't deprecate GIF output to make sure to not break
         * anyone's apps.
         * If gif output is needed then we are using GD
         * to convert our libvips image to a gif.
         *
         * (Feels a little hackish but there is not an alternative at
         * this moment..)
         */

        // Check if GD library is installed on the server
        $gdAvailable = extension_loaded('gd') && function_exists('gd_info');

        // If the GD library is installed and a gif output is needed.
        if ($gdAvailable && $needsGif) {
            // Create GD image from string (suppress any warnings)
            $gdImage = @imagecreatefromstring($buffer);

            // If image is valid
            if ($gdImage !== false) {
                // Enable interlacing if needed
                if ($toBufferOptions['interlace']) {
                    imageinterlace($gdImage, true);
                }

                // Preserve transparency
                if ($params['hasAlpha']) {
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

                // Extension and mime-type are now gif
                $extension = 'gif';
                $mimeType = 'image/gif';
            }
        }

        return [
            $buffer,
            $mimeType,
            $extension
        ];
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
}
