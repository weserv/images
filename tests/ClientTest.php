<?php

namespace AndriesLouw\imagesweserv;

use GuzzleHttp\ClientInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends TestCase
{
    private $client;

    private $tempFile;

    private $options;

    public function setUp()
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit');

        $this->options = [
            'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
            'connect_timeout' => 5,
            'timeout' => 10,
            'max_image_size' => 0,
            'max_redirects' => 10,
            'allowed_mime_types' => []
        ];

        $this->client = new Client($this->tempFile, $this->options);
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Client::class, $this->client);
    }

    public function testSetClient()
    {
        $client = Mockery::mock(ClientInterface::class);
        $this->client->setClient($client);
        $this->assertInstanceOf(ClientInterface::class, $this->client->getClient());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf(ClientInterface::class, $this->client->getClient());
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

        $response = Mockery::mock(ResponseInterface::class);

        $client = Mockery::mock(\GuzzleHttp\Client::class, function ($mock) use ($response, $pixel) {
            $mock->shouldReceive('request')
                ->with('GET', '/', [
                    'sink' => $this->tempFile,
                    'timeout' => $this->options['timeout'],
                    'headers' => [
                        'Accept-Encoding' => 'gzip',
                        'User-Agent' => $this->options['user_agent'],
                    ]
                ])
                ->andReturnUsing(function () use ($response, $pixel) {
                    file_put_contents($this->tempFile, $pixel);

                    return $response;
                })->once();
        });

        $this->client->setClient($client);

        $this->assertSame($this->tempFile, $this->client->get('/'));

        $this->assertStringEqualsFile($this->tempFile, $pixel);

        unlink($this->tempFile);
    }
}
