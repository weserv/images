<?php

namespace Weserv\Images\Test\Manipulators;

use Jcupitt\Vips\Image;
use Mockery\MockInterface;
use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Blur;
use Weserv\Images\Test\ImagesWeservTestCase;

class BlurTest extends ImagesWeservTestCase
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
     * @var Blur
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Blur();
    }

    public function testCreateInstance(): void
    {
        $this->assertInstanceOf(Blur::class, $this->manipulator);
    }

    public function testBlurRadius1(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/blur-1.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'blur' => '1'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurRadius10(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/blur-10.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'blur' => '10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurRadius03(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/blur-0.3.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'blur' => '0.3'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testMildBlur(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/blur-mild.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'blur' => 'true'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurPngTransparent(): void
    {
        $testImage = $this->inputPngOverlayLayer1;
        $expectedImage = $this->expectedDir . '/blur-trans.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'blur' => '10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testGetBlur(): void
    {
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => '50'])->getBlur());
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => 50])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => null])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => 'a'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '-1'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '1001'])->getBlur());
    }
}
