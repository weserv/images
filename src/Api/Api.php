<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Background;
use AndriesLouw\imagesweserv\Manipulators\Blur;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use AndriesLouw\imagesweserv\Manipulators\Shape;
use AndriesLouw\imagesweserv\Manipulators\Sharpen;
use AndriesLouw\imagesweserv\Manipulators\Size;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Jcupitt\Vips\BandFormat;
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
     * {@inheritdoc}
     *
     * @throws ImageNotReadableException if the provided image is not readable.
     * @throws ImageTooLargeException if the provided image is too large for
     *      processing.
     * @throws RequestException for errors that occur during a transfer or during
     *      the on_headers event
     * @throws VipsException for errors that occur during the processing of a Image
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
            } elseif (strpos($url, 'grey-8bit-alpha.png') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/grey-8bit-alpha.png';
            } elseif (strpos($url, 'tbgn2c16.png') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/tbgn2c16.png';
            } elseif (strpos($url, 'lichtenstein.jpg') !== false) {
                $tmpFileName = __DIR__ . '/../../public_html/test-images/lichtenstein.jpg';
            } else {
                $tmpFileName = $this->client->get($url);
            }
        } else {
            $tmpFileName = $this->client->get($url);
        }

        try {
            $image = Image::newFromFile($tmpFileName);
        } catch (VipsException $e) {
            trigger_error('Image not readable. Message: `' . $e->getMessage() . '` URL: ' . $url, E_USER_WARNING);

            // Keep throwing it (with a wrapper).
            throw new ImageNotReadableException('Image not readable. Is it a valid image?', 0, $e);
        }

        $allowed = $this->getAllowedImageTypes();

        $interpretation = $image->interpretation;

        // Put common variables in the parameters
        $params['hasAlpha'] = Utils::hasAlpha($image);
        $params['is16Bit'] = Utils::is16Bit($interpretation);
        $params['maxAlpha'] = Utils::maximumImageAlpha($interpretation);
        $params['isPremultiplied'] = false;

        foreach ($this->manipulators as $manipulator) {
            $manipulator->setParams($params);

            try {
                $image = $manipulator->run($image);
            } catch (ImageTooLargeException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);

                // Keep throwing it.
                throw $e;
            } catch (VipsException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);

                // Keep throwing it.
                throw $e;
            }

            // Size and shape manipulators can override `hasAlpha` parameter.
            if ($manipulator instanceof Size || $manipulator instanceof Shape) {
                $params['hasAlpha'] = $manipulator->hasAlpha;
            }

            // Size, sharpen, blur and background manipulators can override `isPremultiplied` parameter.
            if ($manipulator instanceof Size
                || $manipulator instanceof Sharpen
                || $manipulator instanceof Blur
                || $manipulator instanceof Background
            ) {
                $params['isPremultiplied'] = $manipulator->isPremultiplied;
            }
        }

        // Reverse premultiplication after all transformations:
        if ($params['isPremultiplied']) {
            $image = $image->unpremultiply(['max_alpha' => $params['maxAlpha']]);

            // Cast pixel values to integer
            if ($params['is16Bit']) {
                $image = $image->cast(BandFormat::USHORT);
            } else {
                $image = $image->cast(BandFormat::UCHAR);
            }
        }

        // Check if output is set and allowed
        if (isset($params['output']) && isset($allowed[$params['output']])) {
            $extension = $params['output'];
        } else {
            $supportsAlpha = ['png', 'webp'];
            if ($params['hasAlpha'] && !isset($supportsAlpha[$extension])) {
                // If image has alpha and doesn't have the right extension to output alpha.
                // Then force it to PNG (useful for shape masking and letterboxing).
                $extension = 'png';
            } elseif (!isset($allowed[$extension])) {
                // If extension is not allowed (and doesn't have alpha) we need to output it as jpg.
                $extension = 'jpg';
            }
        }

        $options = [];

        if ($extension == 'jpg' || $extension == 'webp') {
            $options['Q'] = $this->getQuality($params);
        }
        if ($extension == 'jpg' || $extension == 'png') {
            $options['interlace'] = array_key_exists('il', $params);
        }
        if ($extension == 'png') {
            $options['compression'] = $this->getCompressionLevel($params);
        }

        return [
            $image->writeToBuffer('.' . $extension, $options),
            $allowed[$extension],
            $extension
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
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
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
