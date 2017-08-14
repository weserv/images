<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Config;
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
     * Create API instance.
     *
     * @param Client $client The Guzzle
     * @param array $manipulators Collection of manipulators.
     * @throws \InvalidArgumentException if there's a manipulator which not extends
     *      ManipulatorInterface
     */
    public function __construct(Client $client, array $manipulators)
    {
        $this->setClient($client);
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
     * @return Image The image
     */
    public function run(string $url, array $params): Image
    {
        // libvips caching is not needed
        Config::cacheSetMax(0);
        Config::cacheSetMaxFiles(0);
        Config::cacheSetMaxMem(0);

        $tmpFileName = $this->client->get($url);

        // Don't use sequential mode read, if we're doing a trim.
        // (it will scan the whole image once to find the crop area)
        $params['accessMethod'] = isset($params['trim']) || array_key_exists('trim',
            $params) ? Access::RANDOM : Access::SEQUENTIAL;

        // Find the name of the load operation vips will use to load a file
        $params['loader'] = Image::findLoad($tmpFileName);

        // Save our temporary file name
        $params['tmpFileName'] = $tmpFileName;

        try {
            // Create a new Image instance from our temporary file
            $image = Image::newFromFile($tmpFileName, $this->getLoadOptions($params));
        } catch (VipsException $e) {
            // Keep throwing it (with a wrapper).
            throw new ImageNotReadableException('Image not readable. Is it a valid image?', 0, $e);
        }

        // Put common variables in the parameters
        $params['isPremultiplied'] = false;

        // Calculate the angle of rotation and need-to-flip for the given exif orientation and parameters
        list($params['rotation'], $params['flip'], $params['flop']) = Utils::resolveRotationAndFlip($image, $params);

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
            $image = Utils::is16Bit($image->interpretation) ? $image->cast(BandFormat::USHORT) : $image->cast(BandFormat::UCHAR);
        }

        return $image;
    }

    /**
     * Get the options to pass on to the load operation.
     *
     * @param array $params Parameters array (by reference)
     *
     * @return array Any options to pass on to the load operation.
     */
    public function getLoadOptions(array &$params): array
    {
        $loadOptions = [
            'access' => $params['accessMethod']
        ];

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

        return $loadOptions;
    }
}
