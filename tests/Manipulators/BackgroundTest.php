<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Background;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class BackgroundTest extends ImagesweservTestCase
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
     * @var Background
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Background();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Background::class, $this->manipulator);
    }

    public function testFlattenToBlack()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/flatten-black.png';
        $params = [
            'w' => '400',
            'h' => '300',
            't' => 'square',
            'bg' => 'black'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(300, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testFlattenToOrange()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/flatten-orange.png';
        $params = [
            'w' => '400',
            'h' => '300',
            't' => 'square',
            'bg' => 'darkorange'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(300, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testFlattenToOrangeHex()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/flatten-orange.png';
        $params = [
            'w' => '400',
            'h' => '300',
            't' => 'square',
            'bg' => 'FF8C00'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(300, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testFlatten16bitWithTransparencyToOrange()
    {
        $testImage = $this->inputPngWithTransparency16bit;
        $expectedImage = $this->expectedDir . '/flatten-rgb16-orange.png';
        $params = [
            'bg' => 'darkorange'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(32, $image->width);
        $this->assertEquals(32, $image->height);
        $this->assertMaxColorDistance($expectedImage, $image);
    }

    public function testFlattenGreyScaleToOrange()
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $expectedImage = $this->expectedDir . '/flatten-2channel.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'greyscale',
            'bg' => 'darkorange'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(1, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testFlattenBlurToOrangeShouldUnpremultiply()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/flatten-blur-orange.png';
        $params = [
            'w' => '400',
            'h' => '300',
            't' => 'square',
            'blur' => '1',
            'bg' => 'darkorange'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(300, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testCompositeTo50PercentOrange()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/composite-50-orange.png';
        $params = [
            'w' => '400',
            'h' => '300',
            't' => 'square',
            'bg' => '80FF8C00'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(300, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testIgnoreForJpeg()
    {
        $testImage = $this->inputJpg;
        $params = [
            'bg' => 'FF0000'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
    }

    public function testIgnoreForTransparentBackground()
    {
        $testImage = $this->inputPngWithTransparency;
        $params = [
            'bg' => '0FFF'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
    }
}
