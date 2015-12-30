<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use GuzzleHttp\Exception\RequestException;
use Imagick;
use ImagickException;
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
     * @throws ImagickException for errors that occur during image manipulation
     * @return string Manipulated image binary data.
     */
    public function run($url, array $params)
    {
        $tmpFileName = $this->client->get($url);

        try {
            $image = $this->imageManager->make($tmpFileName);

            // Upscale ImageMagick memory limits for 16GB-ram servers (TODO Should we do this in the policy.xml file?)
            if ($image->getDriver()->getDriverName() == 'Imagick') {
                // Get ImageMagick core
                $imagick = $image->getCore();

                // Set the h*w pixel limit that can exist in memory
                $imagick->setResourceLimit(imagick::RESOURCETYPE_AREA, 4e+6);
                // Set maximum disk usage (-1 = infinite)
                $imagick->setResourceLimit(imagick::RESOURCETYPE_DISK, -1);
                // Set maximum number of cache files that can be open at once
                $imagick->setResourceLimit(imagick::RESOURCETYPE_FILE, 768);
                // Set maximum memory map (in bytes) until things are offloaded to disk
                $imagick->setResourceLimit(imagick::RESOURCETYPE_MAP, 256e+6);
                // How much memory to allocate (in bytes)
                $imagick->setResourceLimit(imagick::RESOURCETYPE_MEMORY, 256e+6);
                // Use 2 threads (because we have OpenMP enabled) equivalent of Imagick::setResourceLimit(imagick::RESOURCETYPE_THREAD, 2) or MAGICK_THREAD_LIMIT=2;
                $imagick->setResourceLimit(6, 2);
                // Set max image width of 4000
                $imagick->setResourceLimit(9, 4000);
                // Set max image height of 4000
                $imagick->setResourceLimit(10, 4000);

                // Display resource values (debugging)
                if (true == false) {
                    print("Undefined: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_UNDEFINED));

                    print("<br><br>Area: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_AREA));

                    print("<br><br>Disk: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_DISK));

                    print("<br><br>File: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_FILE));

                    print("<br><br>Map: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_MAP));

                    print("<br><br>Memory: ");
                    print($imagick->getResourceLimit(imagick::RESOURCETYPE_MEMORY));

                    print("<br><br>Thread(s): ");
                    print($imagick->getResourceLimit(6));
                    die;
                }
            }
        } catch (NotReadableException $e) {
            //echo file_get_contents($tmpFileName);
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
            } catch (ImagickException $e) {
                trigger_error($e->getMessage() . ' URL: ' . $url . ' Params: ' . implode(', ', $params),
                    E_USER_WARNING);
                throw $e;
            }

            //gc_collect_cycles();
        }

        $image->destroy();

        return $image->getEncoded();
    }
}
