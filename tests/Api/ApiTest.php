<?php

namespace Weserv\Images\Test\Api;

use Jcupitt\Vips\Access;
use Mockery\MockInterface;
use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\ManipulatorInterface;
use Weserv\Images\Test\ImagesWeservTestCase;

class ApiTest extends ImagesWeservTestCase
{
    private Client $client;

    private Api $api;

    public function setUp(): void
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
    }

    public function testCreateInstance(): void
    {
        $this->assertInstanceOf(Api::class, $this->api);
    }

    public function testSetClient(): void
    {
        $this->api->setClient($this->getMockery(Client::class));
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testGetClient(): void
    {
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testSetManipulators(): void
    {
        $this->api->setManipulators([$this->getMockery(ManipulatorInterface::class)]);
        $manipulators = $this->api->getManipulators();
        $this->assertInstanceOf(ManipulatorInterface::class, $manipulators[0]);
    }

    public function testSetInvalidManipulator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->api->setManipulators([new \StdClass()]);
    }

    public function testGetManipulators(): void
    {
        $this->assertEquals($this->getManipulators(), $this->api->getManipulators());
    }

    public function testGetLoadOptions(): void
    {
        $params = [
            'accessMethod' => Access::SEQUENTIAL,
            'page' => 0,
        ];

        $params['tmpFileName'] = 'test.pdf';
        $params['loader'] = 'VipsForeignLoadPdfFile';
        $this->assertEquals([
            'access' => Access::SEQUENTIAL,
            'page' => 0
        ], $this->api->getLoadOptions($params));
        $this->assertEquals('test.pdf[page=0]', $params['tmpFileName']);

        $params['tmpFileName'] = 'test.tiff';
        $params['loader'] = 'VipsForeignLoadTiffFile';
        $this->assertEquals([
            'access' => Access::SEQUENTIAL,
            'page' => 0
        ], $this->api->getLoadOptions($params));
        $this->assertEquals('test.tiff[page=0]', $params['tmpFileName']);

        $params['tmpFileName'] = 'test.ico';
        $params['loader'] = 'VipsForeignLoadMagickFile';
        $this->assertEquals([
            'access' => Access::SEQUENTIAL,
            'page' => 0
        ], $this->api->getLoadOptions($params));
        $this->assertEquals('test.ico[page=0]', $params['tmpFileName']);

        $params['tmpFileName'] = 'test.jpg';
        $params['loader'] = 'VipsForeignLoadJpegFile';
        $this->assertEquals([
            'access' => Access::SEQUENTIAL
        ], $this->api->getLoadOptions($params));
        $this->assertEquals('test.jpg', $params['tmpFileName']);
    }
}
