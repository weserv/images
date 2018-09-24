<?php

namespace Weserv\Images\Test\Manipulators;

use Jcupitt\Vips\Image;
use Mockery\MockInterface;
use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Filter;
use Weserv\Images\Test\ImagesWeservTestCase;

class FilterTest extends ImagesWeservTestCase
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
     * @var Filter
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Filter();
    }

    public function testCreateInstance(): void
    {
        $this->assertInstanceOf(Filter::class, $this->manipulator);
    }

    public function testGreyscaleFilter(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/greyscale.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'greyscale'
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

    public function testSepiaFilterJpeg(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/sepia.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'sepia'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testSepiaFilterPngTransparent(): void
    {
        $testImage = $this->inputPngOverlayLayer1;
        $expectedImage = $this->expectedDir . '/sepia-trans.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'sepia'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterJpeg(): void
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/negate.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterPng(): void
    {
        $testImage = $this->inputPng;
        $expectedImage = $this->expectedDir . '/negate.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterPngTransparent(): void
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/negate-trans.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterPngWithGreyAlpha(): void
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $expectedImage = $this->expectedDir . '/negate-alpha.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterWebp(): void
    {
        $testImage = $this->inputWebP;
        $expectedImage = $this->expectedDir . '/negate.webp';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testNegateFilterWebpTransparent(): void
    {
        $testImage = $this->inputWebPWithTransparency;
        $expectedImage = $this->expectedDir . '/negate-trans.webp';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'filt' => 'negate'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }
}
