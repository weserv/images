<?php

namespace Weserv\Images\Test\Exception;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Weserv\Images\Client;
use Weserv\Images\Exception\ImageTooBigException;
use Weserv\Images\Test\ImagesWeservTestCase;

class ImageTooBigExceptionTest extends ImagesWeservTestCase
{
    private string $tempFile;

    public function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        unlink($this->tempFile);
    }

    /**
     * Test can construct and throw an exception.
     */
    public function testThrowException(): void
    {
        $this->expectException(ImageTooBigException::class);
        throw new ImageTooBigException();
    }

    public function testImageTooBigException(): void
    {
        $this->expectException(ImageTooBigException::class);
        $this->expectExceptionMessage('2 KB');

        $mock = new MockHandler([
            new Response(200, ['Content-Length' => 2048])
        ]);

        $handler = HandlerStack::create($mock);

        $options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://wsrv.nl/)',
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
