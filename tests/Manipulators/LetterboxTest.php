<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Letterbox;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;
use Mockery\MockInterface;

class LetterboxTest extends ImagesweservTestCase
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
     * @var Letterbox
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Letterbox();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Letterbox::class, $this->manipulator);
    }

    /**
     * TIFF letterbox known to cause rounding errors
     */
    public function testTiffLetterbox()
    {
        $testImage = $this->inputTiff;
        $params = [
            'w' => '240',
            'h' => '320',
            't' => 'letterbox',
            'bg' => 'white'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(240, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Letterbox TIFF in LAB colourspace onto RGBA background
     */
    public function testTiffLetterboxOnRGBA()
    {
        $testImage = $this->inputTiffCielab;
        $expectedImage = $this->expectedDir . '/letterbox-lab-into-rgba.png';
        $params = [
            'w' => '64',
            'h' => '128',
            't' => 'letterbox',
            'bg' => '80FF6600',
            'output' => 'png'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(64, $image->width);
        $this->assertEquals(128, $image->height);

        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * From CMYK to sRGB with white background, not yellow
     */
    public function testCMYKTosRGBWithBackground()
    {
        $testImage = $this->inputJpgWithCmykProfile;
        $expectedImage = $this->expectedDir . '/colourspace.cmyk.jpg';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'letterbox',
            'bg' => 'white'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(Interpretation::RGB, $image->interpretation);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * PNG with alpha channel
     */
    public function testLetterboxPngTransparent()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/letterbox-4-into-4.png';
        $params = [
            'w' => '50',
            'h' => '50',
            't' => 'letterbox',
            'bg' => 'white'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
        $this->assertEquals(50, $image->width);
        $this->assertEquals(50, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * 16-bit PNG with alpha channel
     */
    public function testLetterbox16bitWithTransparency()
    {
        $testImage = $this->inputPngWithTransparency16bit;
        $expectedImage = $this->expectedDir . '/letterbox-16bit.png';
        $params = [
            'w' => '32',
            'h' => '16',
            't' => 'letterbox',
            'bg' => 'white'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
        $this->assertEquals(32, $image->width);
        $this->assertEquals(16, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * 16-bit PNG with alpha channel onto RGBA
     */
    public function testLetterbox16bitWithTransparencyOnRGBA()
    {
        $testImage = $this->inputPngWithTransparency16bit;
        $expectedImage = $this->expectedDir . '/letterbox-16bit-rgba.png';
        $params = [
            'w' => '32',
            'h' => '16',
            't' => 'letterbox'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(32, $image->width);
        $this->assertEquals(16, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * PNG with 2 channels
     */
    public function testLetterboxPng2Channels()
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $expectedImage = $this->expectedDir . '/letterbox-2channel.png';
        $params = [
            'w' => '32',
            'h' => '16',
            't' => 'letterbox'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(32, $image->width);
        $this->assertEquals(16, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    /**
     * Enlarge and letterbox
     */
    public function testLetterboxEnlarge()
    {
        $testImage = $this->inputPngWithOneColor;
        $expectedImage = $this->expectedDir . '/letterbox-enlarge.png';
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'letterbox',
            'bg' => 'black'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }
}
