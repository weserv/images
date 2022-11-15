<?php

namespace Weserv\Images\Test\Manipulators;

use Mockery\MockInterface;
use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Helpers\Utils;
use Weserv\Images\Manipulators\Orientation;
use Weserv\Images\Test\ImagesWeservTestCase;

class OrientationTest extends ImagesWeservTestCase
{
    private Client $client;

    private Api $api;

    private Orientation $manipulator;

    public function setUp(): void
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Orientation();
    }

    public function testCreateInstance(): void
    {
        $this->assertInstanceOf(Orientation::class, $this->manipulator);
    }

    /**
     * Rotate by any 90-multiple angle
     */
    public function testRotateBy90MultipleAngle(): void
    {
        $testImage = $this->inputJpg320x240;

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        foreach ([-3690, -450, -90, 90, 450, 3690] as $angle) {
            $params = [
                'or' => $angle
            ];

            $image = $this->api->run($uri, $params);

            $this->assertEquals(240, $image->width);
            $this->assertEquals(320, $image->height);
            $this->assertFalse($image->hasAlpha());
        }
    }

    /**
     * Rotate by any 180-multiple angle
     */
    public function testRotateBy180MultipleAngle(): void
    {
        $testImage = $this->inputJpg320x240;

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        foreach ([-3780, -540, 0, 180, 540, 3780] as $angle) {
            $params = [
                'or' => $angle
            ];

            $image = $this->api->run($uri, $params);

            $this->assertEquals(320, $image->width);
            $this->assertEquals(240, $image->height);
            $this->assertFalse($image->hasAlpha());
        }
    }

    /**
     * EXIF Orientation, auto-rotate
     */
    public function testAutoRotate(): void
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

                $image = $this->api->run($uri, $params);

                $this->assertEquals(320, $image->width);
                $this->assertEquals($orientation === 'Landscape' ? 240 : 427, $image->height);
                $this->assertFalse($image->hasAlpha());

                // Check if the EXIF orientation header is removed
                $this->assertEquals(0, $image->typeof(Utils::VIPS_META_ORIENTATION));

                $this->assertSimilarImage($expectedImage, $image);
            }
        }
    }
}
