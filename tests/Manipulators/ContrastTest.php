<?php

namespace Weserv\Images\Test\Manipulators;

use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Contrast;
use Weserv\Images\Test\ImagesWeservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class ContrastTest extends ImagesWeservTestCase
{
    /**
     * @var Client|MockInterface
     */
    private $client;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var Contrast
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Contrast();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Contrast::class, $this->manipulator);
    }

    public function testContrastIncrease()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/contrast-increase.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'con' => '30'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testContrastDecrease()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/contrast-decrease.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'con' => '-30'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testGetContrast()
    {
        $this->assertSame(50, $this->manipulator->setParams(['con' => '50'])->getContrast());
        $this->assertSame(50, $this->manipulator->setParams(['con' => 50])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => null])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '-101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => 'a'])->getContrast());
    }
}
