<?php

namespace Weserv\Images\Test\Manipulators;

use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Shape;
use Weserv\Images\Test\ImagesWeservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class ShapeTest extends ImagesWeservTestCase
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
     * @var Shape
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Shape();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Shape::class, $this->manipulator);
    }

    public function testCircleShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-circle.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'circle'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testCircleShapeTrim()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-circle-trim.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'circle',
            'strim' => 'true',
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(240, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testEllipseShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-ellipse.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'ellipse'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testTriangleShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-triangle.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'triangle'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testTriangle180Shape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-triangle-180.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'triangle-180'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testPentagonShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-pentagon.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'pentagon'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testPentagon180Shape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-pentagon-180.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'pentagon-180'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testHexagonShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-hexagon-180.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'hexagon'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testSquareShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-square.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'square'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testStarShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-star.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'star'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testHeartShape()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/shape-heart.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'heart'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testShapePngTransparent()
    {
        $testImage = $this->inputPngOverlayLayer0;
        $expectedImage = $this->expectedDir . '/shape-star-trans.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'star'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * PNG with 2 channels
     */
    public function testShapePng2Channels()
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $expectedImage = $this->expectedDir . '/shape-2channel.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'shape' => 'triangle-180'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testGetShape()
    {
        $this->assertSame('circle', $this->manipulator->setParams(['shape' => 'circle'])->getShape());
        $this->assertSame('ellipse', $this->manipulator->setParams(['shape' => 'ellipse'])->getShape());
        $this->assertSame('hexagon', $this->manipulator->setParams(['shape' => 'hexagon'])->getShape());
        $this->assertSame('pentagon', $this->manipulator->setParams(['shape' => 'pentagon'])->getShape());
        $this->assertSame('pentagon-180', $this->manipulator->setParams(['shape' => 'pentagon-180'])->getShape());
        $this->assertSame('square', $this->manipulator->setParams(['shape' => 'square'])->getShape());
        $this->assertSame('star', $this->manipulator->setParams(['shape' => 'star'])->getShape());
        $this->assertSame('heart', $this->manipulator->setParams(['shape' => 'heart'])->getShape());
        $this->assertSame('triangle', $this->manipulator->setParams(['shape' => 'triangle'])->getShape());
        $this->assertSame('triangle-180', $this->manipulator->setParams(['shape' => 'triangle-180'])->getShape());
        $this->assertSame('circle', $this->manipulator->setParams(['circle' => true])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => null])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => 'a'])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => '-1'])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => '100'])->getShape());
    }
}
