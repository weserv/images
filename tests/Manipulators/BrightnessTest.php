<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Brightness;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class BrightnessTest extends ImagesweservTestCase
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
     * @var Brightness
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Brightness();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Brightness::class, $this->manipulator);
    }

    public function testBrightnessIncrease()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/brightness-increase.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'bri' => '30'
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

    public function testBrightnessDecrease()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/brightness-decrease.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'bri' => '-30'
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

    public function testBrightnessPngTransparent()
    {
        $testImage = $this->inputPngOverlayLayer1;
        $expectedImage = $this->expectedDir . '/brightness-trans.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'bri' => '30'
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

    public function testGetBrightness()
    {
        $this->assertSame(50, $this->manipulator->setParams(['bri' => '50'])->getBrightness());
        $this->assertSame(50, $this->manipulator->setParams(['bri' => 50])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => null])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => '101'])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => '-101'])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => 'a'])->getBrightness());
    }
}
