<?php

namespace AndriesLouw\imagesweserv\Test;

use AndriesLouw\imagesweserv\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends ImagesweservTestCase
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

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Client::class, $this->client);
    }

    public function testSetClient()
    {
        $client = $this->getMockery(ClientInterface::class);
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
}
