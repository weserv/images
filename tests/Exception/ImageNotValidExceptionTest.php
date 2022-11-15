<?php

namespace Weserv\Images\Test\Exception;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Weserv\Images\Client;
use Weserv\Images\Exception\ImageNotValidException;
use Weserv\Images\Test\ImagesWeservTestCase;

class ImageNotValidExceptionTest extends ImagesWeservTestCase
{
    private $tempFile;

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
        $this->expectException(ImageNotValidException::class);
        throw new ImageNotValidException();
    }

    public function testImageNotValidException(): void
    {
        $this->expectException(ImageNotValidException::class);
    
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/zip'])
        ]);

        $handler = HandlerStack::create($mock);

        $options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://wsrv.nl/)',
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
