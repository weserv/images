<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageProcessingException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
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
     * The current mime type
     *
     * @var Client
     */
    protected $mimeType;

    /**
     * Create API instance.
     *
     * @param Client $client The Guzzle
     * @param array $manipulators Collection of manipulators.
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
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the PHP HTTP client
     *
     * @param Client $client Guzzle client
     *
     * @return void
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
     *
     * @return void
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
     * @param string $url Source URL
     * @param string $extension Extension of URL
     * @param array $params The manipulation parameters.
     *
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws RequestException for errors that occur during a transfer or during
     *      the on_headers event
     * @throws ImageProcessingException for errors that occur during the processing of a Image
     *
     * @return array [
     *      'image' => *Manipulated image binary data*,
     *      'type' => *The mimetype*,
     *      'extension' => *The extension*
     * ]
     */
    public function run(string $url, string $extension, array $params): array
    {
        // Debugging
        $debug = true;

        if ($debug) {
            if (strpos($url, 'PNG_transparency_demonstration_1.png') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/example.png';
            } elseif (strpos($url, 'orientation.jpg') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/orientation.jpg';
            } elseif (strpos($url, 'lichtenstein.jpg') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/lichtenstein.jpg';
            } else {
                $tmpFileName = $this->client->get($url);
            }
        } else {
            $tmpFileName = $this->client->get($url);
        }

        $image = Image::newFromFile($tmpFileName);
        //$image->setLogging($debug);

        $allowed = $this->getAllowedImageTypes();

        if ($image === null) {
            @unlink($tmpFileName);
            trigger_error('Image not readable. URL: ' . $url, E_USER_WARNING);
        }

        if (!isset($params['output'])) {
            if (array_key_exists($extension, $allowed)) {
                $params['output'] = $extension;
            } else {
                $params['output'] = 'jpg';
            }
        }

        $interpretation = $image->interpretation;

        // Put common variables in the parameters
        $params['hasAlpha'] = Utils::hasAlpha($image);
        $params['is16Bit'] = Utils::is16Bit($interpretation);
        $params['maxAlpha'] = Utils::maximumImageAlpha($interpretation);

        foreach ($this->manipulators as $manipulator) {
            $manipulator->setParams($params);

            try {
                $image = $manipulator->run($image);
            } catch (ImageTooLargeException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);
                throw $e;
            } catch (ImageProcessingException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);
                throw $e;
            }
        }

        // Reverse premultiplication after all transformations:
        if (Utils::isPremultiplied($image)) {
            //$image->set(Utils::EXIF_IS_PREMULTIPLIED, 'false');
            $image = $image->unpremultiply(['max_alpha' => $params['maxAlpha']]);

            // Cast pixel values to integer
            if ($params['is16Bit']) {
                $image = $image->cast(Utils::VIPS_FORMAT_USHORT);
            } else {
                $image = $image->cast(Utils::VIPS_FORMAT_UCHAR);
            }
        }

        if (array_key_exists($params['output'], $allowed)) {
            $extension = $params['output'];
        }

        $options = [];

        if ($extension == 'jpg' || $extension == 'webp' || $extension == 'tiff') {
            $options['Q'] = $this->getQuality($params);
        }
        if ($extension == 'jpg' || $extension == 'png') {
            $options['interlace'] = array_key_exists('il', $params);
        }
        if ($extension == 'png') {
            $options['compression'] = $this->getCompressionLevel($params);
        }

        return [
            'image' => $image->writeToBuffer('.' . $extension, $options),
            'type' => $allowed[$extension],
            'extension' => $extension
        ];
    }

    /**
     * Get the allowed image types to convert to.
     *
     * @return array
     */
    public function getAllowedImageTypes(): array
    {
        return [
            //'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'tiff' => 'image/tiff',
            'webp' => 'image/webp',
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

        if ($params['q'] < 0 || $params['q'] > 100) {
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
