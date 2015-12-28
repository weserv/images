<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use GuzzleHttp\Exception\RequestException;
use Intervention\Image\Exception\InvalidArgumentException;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Exception\RuntimeException;
use Intervention\Image\ImageManager;

class Api implements ApiInterface
{
    /**
     * Intervention image manager.
     * @var ImageManager
     */
    protected $imageManager;

    /**
     * Collection of manipulators.
     * @var ManipulatorInterface[]
     */
    protected $manipulators;

    /**
     * The PHP HTTP client
     * @var Client
     */
    protected $client;

    /**
     * The current mime type
     * @var Client
     */
    protected $mimeType;

    /**
     * Create API instance.
     * @param ImageManager $imageManager Intervention image manager.
     * @param array $manipulators Collection of manipulators.
     */
    public function __construct(ImageManager $imageManager, Client $client, array $manipulators)
    {
        $this->setImageManager($imageManager);
        $this->setClient($client);
        $this->setManipulators($manipulators);
    }

    /**
     * Get the image manager.
     * @return ImageManager Intervention image manager.
     */
    public function getImageManager()
    {
        return $this->imageManager;
    }

    /**
     * Set the image manager.
     * @param ImageManager $imageManager Intervention image manager.
     */
    public function setImageManager(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * Get the PHP HTTP client
     * @return ImageManager Intervention image manager.
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Set the PHP HTTP client
     * @param Client $client Guzzle client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Get the manipulators.
     * @return array Collection of manipulators.
     */
    public function getManipulators()
    {
        return $this->manipulators;
    }

    /**
     * Set the manipulators.
     * @param array $manipulators Collection of manipulators.
     * @throws InvalidArgumentException if there's a manipulator which not extends ManipulatorInterface
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
     * @param  string $url Source URL
     * @param  array $params The manipulation params.
     * @throws NotReadableException if the provided file can not be read
     * @throws ImageTooLargeException if the provided image is too large for processing.
     * @throws RequestException for errors that occur during a transfer or during the on_headers event
     * @return \Intervention\Image\Image
     */
    public function run($url, array $params)
    {
        $tmpFileName = $this->client->get($url);

        try {
            $image = $this->imageManager->make($tmpFileName);
        } catch (NotReadableException $e) {
            @unlink($tmpFileName);
            trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);
            throw $e;
        }

        foreach ($this->manipulators as $manipulator) {
            $manipulator->setParams($params);

            try {
                $image = $manipulator->run($image);
            } catch (ImageTooLargeException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url, E_USER_WARNING);
                throw $e;
            } catch (RuntimeException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url . ' Params: ' . implode(', ', $params),
                    E_USER_WARNING);
                throw $e;
            }
        }

        return $image;
    }
}
