<?php

namespace AndriesLouw\imagesweserv;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private $client;

    private $tempFile;

    private $options;

    public function setUp()
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        rename($this->tempFile, $this->tempFile .= '.png');

        $this->options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
            'connect_timeout' => 5,
            'timeout' => 10,
            'max_image_size' => 0,
            'max_redirects' => 10,
            'allowed_mime_types' => [],
            'error_message' => [
                'invalid_image' => [
                    'header' => '400 Bad Request',
                    'content-type' => 'text/plain',
                    'message' => 'The request image is not a valid (supported) image. Supported images are: %s',
                    'log' => 'Non-supported image. URL: %s',
                ],
                'image_too_big' => [
                    'header' => '400 Bad Request',
                    'content-type' => 'text/plain',
                    'message' => 'The image is too big to be downloaded.' . PHP_EOL . 'Image size %s'
                        . PHP_EOL . 'Max image size: %s',
                    'log' => 'Image too big. URL: %s',
                ],
                'curl_error' => [
                    'header' => '400 Bad Request',
                    'content-type' => 'text/plain',
                    'message' => 'cURL Request error: %s Status code: %s',
                    'log' => 'cURL Request error: %s URL: %s Status code: %s',
                ]
            ]
        ];

        $this->client = new Client($this->tempFile, $this->options);
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Client', $this->client);
    }

    public function testSetClient()
    {
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $this->client->setClient($client);
        $this->assertInstanceOf('GuzzleHttp\ClientInterface', $this->client->getClient());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf('GuzzleHttp\ClientInterface', $this->client->getClient());
    }

    public function testGetOptions()
    {
        $this->assertSame($this->options, $this->client->getOptions());
    }

    public function testOutputClient()
    {
        /* 1x1 transparent pixel */
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $pixel = base64_decode($base64);

        $response = Mockery::mock('GuzzleHttp\Message\ResponseInterface');

        $client = Mockery::mock('GuzzleHttp\Client', function (MockInterface $mock) use ($response, $pixel) {
            $mock->shouldReceive('get')->andReturnUsing(function () use ($response, $pixel) {
                file_put_contents($this->tempFile, $pixel);

                return $response;
            })->once();
        });

        $this->client->setClient($client);

        $this->assertSame($this->tempFile, $this->client->get('/'));

        $this->assertSame($pixel, file_get_contents($this->tempFile));

        unlink($this->tempFile);
    }
}
