<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
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
use Predis\Connection\ConnectionException;

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
     * Create API instance.
     *
     * @param Client $client The Guzzle
     * @param ThrottlerInterface|null $throttler Throttler
     * @param array $manipulators Collection of manipulators.
     * @throws \InvalidArgumentException if there's a manipulator which not extends
     *      ManipulatorInterface
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
    public function getClient(): Client
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
     * @throws ImageNotValidException if the requested image is not a valid
     *      image.
     * @throws ImageTooBigException if the requested image is too big to be
     *      downloaded.
     * @throws ImageNotReadableException if the provided image is not readable.
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws RequestException for errors that occur during a transfer or during
     *      the on_headers event
     * @throws VipsException for errors that occur during the processing of a Image
     *
     * @return array [
     * @type Image The image,
     * @type string The extension of the image,
     * @type bool Does the image has alpha?
     * ]
     */
    public function run(string $url, array $params): array
    {
        // Throttler can be null
        if ($this->throttler) {
            // For PHPUnit check if REMOTE_ADDR is set
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // Check if rate is exceeded for IP
            try {
                if ($this->throttler->isExceeded($ip)) {
                    throw new RateExceededException('There are an unusual number of requests coming from this IP address.');
                }
            } catch (ConnectionException $e) {
                // Log redis exceptions
                trigger_error('RedisException. Message: ' . $e->getMessage(), E_USER_WARNING);
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
        $isTrim = isset($params['trim']) || array_key_exists('trim', $params);

        // If any of the above adjustments; don't use sequential mode read.
        $params['accessMethod'] = $isTrim ? Access::RANDOM : Access::SEQUENTIAL;

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

            // Add page to the temporary file parameter
            // Useful for the thumbnail operator
            $params['tmpFileName'] .= '[page=' . $loadOptions['page'] . ']';
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

        // Put common variables in the parameters
        $params['hasAlpha'] = $image->hasAlpha();
        $params['is16Bit'] = Utils::is16Bit($image->interpretation);
        $params['isPremultiplied'] = false;

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

        return [$image, $extension, $params['hasAlpha']];
    }
}
