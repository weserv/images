<?php

namespace Weserv\Images\Test\Exception;

use Weserv\Images\Client;
use Weserv\Images\Exception\ImageNotValidException;
use Weserv\Images\Test\ImagesWeservTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ImageNotValidExceptionTest extends ImagesWeservTestCase
{
    /**
     * @var string
     */
    private $tempFile;

    public function setUp()
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
    }

    public function tearDown()
    {
        parent::tearDown();
        unlink($this->tempFile);
    }

    /**
     * Test can construct and throw an exception.
     *
     * @expectedException \Weserv\Images\Exception\ImageNotValidException
     */
    public function testThrowException()
    {
        throw new ImageNotValidException();
    }

    /**
     * @expectedException \Weserv\Images\Exception\ImageNotValidException
     */
    public function testImageNotValidException()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/zip'])
        ]);

        $handler = HandlerStack::create($mock);

        $options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
            'connect_timeout' => 5,
            'timeout' => 10,
            'max_image_size' => 0,
            'max_redirects' => 10,
            'allowed_mime_types' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
                'image/tiff' => 'tiff',
                'image/webp' => 'webp',
                'image/x-icon' => 'ico',
                'image/vnd.microsoft.icon' => 'ico'
            ]
        ];

        $client = new Client($this->tempFile, $options, ['handler' => $handler]);
        $client->get('image.zip');
    }
}
