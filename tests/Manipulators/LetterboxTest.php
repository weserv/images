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

        $this->assertEquals('tiffload', $image->get('vips-loader'));
        $this->assertEquals(240, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
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

        $this->assertEquals('jpegload', $image->get('vips-loader'));
        $this->assertEquals(Interpretation::RGB, $image->interpretation);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }
}
