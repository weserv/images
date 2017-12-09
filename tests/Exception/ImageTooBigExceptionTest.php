<?php

namespace AndriesLouw\imagesweserv\Test\Exception;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ImageTooBigExceptionTest extends ImagesweservTestCase
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
     * @expectedException \AndriesLouw\imagesweserv\Exception\ImageTooBigException
     */
    public function testThrowException()
    {
        throw new ImageTooBigException();
    }

    /**
     * @expectedException        \AndriesLouw\imagesweserv\Exception\ImageTooBigException
     * @expectedExceptionMessage 2 KB
     */
    public function testImageTooBigException()
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Length' => 2048])
        ]);

        $handler = HandlerStack::create($mock);

        $options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
            'connect_timeout' => 5,
            'timeout' => 10,
            'max_image_size' => 1024,
            'max_redirects' => 10,
            'allowed_mime_types' => []
        ];

        $client = new Client($this->tempFile, $options, ['handler' => $handler]);
        $client->get('image.jpg');
    }
}
