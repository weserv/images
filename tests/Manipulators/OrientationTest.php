<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Manipulators\Orientation;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class OrientationTest extends ImagesweservTestCase
{
    /**
     * @var Client|MockInterface $client
     */
    private $client;

    /**
     * @var Api $api
     */
    private $api;

    /**
     * @var Orientation $manipulator
     */
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Orientation();
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Orientation::class, $this->manipulator);
    }

    /*
     * Rotate by any 90-multiple angle
     */
    public function testRotateBy90MultipleAngle()
    {
        $testImage = $this->inputJpg320x240;

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        foreach ([-3690, -450, -90, 90, 450, 3690] as $angle) {
            $params = [
                'or' => $angle
            ];

            /** @var Image $image */
            $image = $this->api->run($uri, $params);

            $this->assertEquals('jpegload', $image->get('vips-loader'));
            $this->assertEquals(240, $image->width);
            $this->assertEquals(320, $image->height);
            $this->assertFalse($image->hasAlpha());
        }
    }

    /*
     * Rotate by any 180-multiple angle
     */
    public function testRotateBy180MultipleAngle()
    {
        $testImage = $this->inputJpg320x240;

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        foreach ([-3780, -540, 0, 180, 540, 3780] as $angle) {
            $params = [
                'or' => $angle
            ];

            /** @var Image $image */
            $image = $this->api->run($uri, $params);

            $this->assertEquals('jpegload', $image->get('vips-loader'));
            $this->assertEquals(320, $image->width);
            $this->assertEquals(240, $image->height);
            $this->assertFalse($image->hasAlpha());
        }
    }

    /*
     * EXIF Orientation, auto-rotate
     */
    public function testAutoRotate()
    {
        foreach (['Landscape', 'Portrait'] as $orientation) {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $exifTag) {
                $fixture = 'inputJpgWith' . $orientation . 'Exif' . $exifTag;

                $testImage = $this->{$fixture};
                $expectedImage = $this->expectedDir . '/' . $orientation . '_' . $exifTag . '-out.jpg';

                $uri = basename($testImage);

                $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

                $params = [
                    'w' => '320'
                ];

                /** @var Image $image */
                $image = $this->api->run($uri, $params);

                $this->assertEquals('jpegload', $image->get('vips-loader'));
                $this->assertEquals(320, $image->width);
                $this->assertEquals($orientation === 'Landscape' ? 240 : 427, $image->height);
                $this->assertFalse($image->hasAlpha());

                if($exifTag !== 1) {
                    // Check if the EXIF orientation header is removed
                    $this->assertEquals(0, $image->typeof(Utils::VIPS_META_ORIENTATION));
                }

                $this->assertSimilarImage($expectedImage, $image);
            }
        }
    }
}
