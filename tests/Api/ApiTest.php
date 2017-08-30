<?php

namespace AndriesLouw\imagesweserv\Test\Api;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;

class ApiTest extends ImagesweservTestCase
{
    private $client;
    private $api;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Api::class, $this->api);
    }

    public function testSetClient()
    {
        $this->api->setClient($this->getMockery(Client::class));
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testSetManipulators()
    {
        $this->api->setManipulators([$this->getMockery(ManipulatorInterface::class)]);
        $manipulators = $this->api->getManipulators();
        $this->assertInstanceOf(ManipulatorInterface::class, $manipulators[0]);
    }

    public function testSetInvalidManipulator()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->api->setManipulators([new \StdClass()]);
    }

    public function testGetManipulators()
    {
        $this->assertEquals($this->getManipulators(), $this->api->getManipulators());
    }

    public function testImageNotReadable()
    {
        $this->expectException(ImageNotReadableException::class);

        $testImage = $this->fixturesDir . '/foobar.jpg';
        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        $api = new Api($this->client, $this->getManipulators());
        $api->run($uri, []);
    }
}
