<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Blur;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class BlurTest extends ImagesweservTestCase
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

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Blur::class, $this->manipulator);
    }

    public function testBlurRadius1()
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

        $this->assertEquals('jpegload', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurRadius10()
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

        $this->assertEquals('jpegload', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurRadius03()
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

        $this->assertEquals('jpegload', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testMildBlur()
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

        $this->assertEquals('jpegload', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testBlurPngTransparent()
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

        $this->assertEquals('pngload', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testGetBlur()
    {
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => '50'])->getBlur());
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => 50])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => null])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => 'a'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '-1'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '1001'])->getBlur());
    }
}
