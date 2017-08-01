<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Letterbox;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Image;

class LetterboxTest extends ImagesweservTestCase
{
    private $client;
    private $api;

    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, null, $this->getManipulators());
        $this->manipulator = new Letterbox();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Letterbox::class, $this->manipulator);
    }

    /*
     * TIFF letterbox known to cause rounding errors
     */
    public function testTiffEmbed()
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('tiff', $extension);
        $this->assertEquals(240, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }
}
