<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\Crop;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class CropTest extends ImagesweservTestCase
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
     * @var Crop $manipulator
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Crop();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Crop::class, $this->manipulator);
    }

    public function testCropPosition()
    {
        $cropPositions = [
            [
                'name' => 'Top left',
                'width' => 320,
                'height' => 80,
                'position' => 'top-left',
                'fixture' => 'position-top.jpg'
            ],
            [
                'name' => 'Top left',
                'width' => 80,
                'height' => 320,
                'position' => 'top-left',
                'fixture' => 'position-left.jpg'
            ],
            [
                'name' => 'Top',
                'width' => 320,
                'height' => 80,
                'position' => 'top',
                'fixture' => 'position-top.jpg'
            ],
            [
                'name' => 'Top right',
                'width' => 320,
                'height' => 80,
                'position' => 'top-right',
                'fixture' => 'position-top.jpg'
            ],
            [
                'name' => 'Top right',
                'width' => 80,
                'height' => 320,
                'position' => 'top-right',
                'fixture' => 'position-right.jpg'
            ],
            [
                'name' => 'Left',
                'width' => 80,
                'height' => 320,
                'position' => 'left',
                'fixture' => 'position-left.jpg'
            ],
            [
                'name' => 'Center',
                'width' => 320,
                'height' => 80,
                'position' => 'center',
                'fixture' => 'position-center.jpg'
            ],
            [
                'name' => 'Centre',
                'width' => 80,
                'height' => 320,
                'position' => 'center',
                'fixture' => 'position-centre.jpg'
            ],
            [
                'name' => 'Default (centre)',
                'width' => 80,
                'height' => 320,
                'position' => null,
                'fixture' => 'position-centre.jpg'
            ],
            [
                'name' => 'Right',
                'width' => 80,
                'height' => 320,
                'position' => 'right',
                'fixture' => 'position-right.jpg'
            ],
            [
                'name' => 'Bottom left',
                'width' => 320,
                'height' => 80,
                'position' => 'bottom-left',
                'fixture' => 'position-bottom.jpg'
            ],
            [
                'name' => 'Bottom left',
                'width' => 80,
                'height' => 320,
                'position' => 'bottom-left',
                'fixture' => 'position-left.jpg'
            ],
            [
                'name' => 'Bottom',
                'width' => 320,
                'height' => 80,
                'position' => 'bottom',
                'fixture' => 'position-bottom.jpg'
            ],
            [
                'name' => 'Bottom right',
                'width' => 320,
                'height' => 80,
                'position' => 'bottom-right',
                'fixture' => 'position-bottom.jpg'
            ],
            [
                'name' => 'Bottom right',
                'width' => 80,
                'height' => 320,
                'position' => 'bottom-right',
                'fixture' => 'position-right.jpg'
            ]
        ];

        foreach ($cropPositions as $crop) {
            $testImage = $this->inputJpg;
            $expectedImage = $this->expectedDir . '/' . $crop['fixture'];
            $params = [
                'w' => (string)$crop['width'],
                'h' => (string)$crop['height'],
                't' => 'square',
                'a' => $crop['position'],
            ];

            $uri = basename($testImage);

            $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);


            /** @var Image $image */
            $image = $this->api->run($uri, $params);

            $this->assertEquals($crop['width'], $image->width);
            $this->assertEquals($crop['height'], $image->height);
            $this->assertSimilarImage($expectedImage, $image);
        }
    }

    public function testEntropyCropJpeg()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/crop-strategy-entropy.jpg';
        $params = [
            'w' => '80',
            'h' => '320',
            't' => 'square',
            'a' => 'entropy'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
        $this->assertEquals(80, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testEntropyCropPng()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/crop-strategy.png';
        $params = [
            'w' => '320',
            'h' => '80',
            't' => 'square',
            'a' => 'entropy'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(80, $image->height);
        $this->assertTrue($image->hasAlpha());
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testAttentionCropJpg()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/crop-strategy-attention.jpg';
        $params = [
            'w' => '80',
            'h' => '320',
            't' => 'square',
            'a' => 'attention'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3, $image->bands);
        $this->assertEquals(80, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testAttentionCropPng()
    {
        $testImage = $this->inputPngWithTransparency;
        $expectedImage = $this->expectedDir . '/crop-strategy.png';
        $params = [
            'w' => '320',
            'h' => '80',
            't' => 'square',
            'a' => 'attention'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(4, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(80, $image->height);
        $this->assertTrue($image->hasAlpha());
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testPartialImageExtractJpeg()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/extract.jpg';
        $params = [
            'crop' => '20,20,2,2'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(20, $image->width);
        $this->assertEquals(20, $image->height);
        $this->assertSimilarImage($expectedImage, $image, 12);
    }

    public function testPartialImageExtractPng()
    {
        $testImage = $this->inputPng;
        $expectedImage = $this->expectedDir . '/extract.png';
        $params = [
            'crop' => '400,200,200,300'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(400, $image->width);
        $this->assertEquals(200, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testPartialImageExtractWebP()
    {
        $testImage = $this->inputWebP;
        $expectedImage = $this->expectedDir . '/extract.webp';
        $params = [
            'crop' => '125,200,100,50'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(125, $image->width);
        $this->assertEquals(200, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testPartialImageExtractTiff()
    {
        $testImage = $this->inputTiff;
        $expectedImage = $this->expectedDir . '/extract.tiff';
        $params = [
            'crop' => '341,529,34,63'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(341, $image->width);
        $this->assertEquals(529, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testImageResizeAndExtractSvg72DPI()
    {
        $testImage = $this->inputSvg;
        $expectedImage = $this->expectedDir . '/svg72.png';
        $params = [
            'w' => '1024',
            't' => 'fitup',
            'crop' => '40,40,290,760'
        ];

        // @var Image $image
        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        $image = $this->api->run($uri, $params);

        $this->assertEquals(40, $image->width);
        $this->assertEquals(40, $image->height);
        $this->assertSimilarImage($expectedImage, $image, 7);
    }

    public function testImageResizeCropAndExtract()
    {
        $testImage = $this->inputJpg;
        $expectedImage = $this->expectedDir . '/resize-crop-extract.jpg';
        $params = [
            'w' => '500',
            'h' => '500',
            't' => 'square',
            'a' => 'top',
            'crop' => '100,100,10,10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(100, $image->width);
        $this->assertEquals(100, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testRotateAndExtract()
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $expectedImage = $this->expectedDir . '/rotate-extract.jpg';
        $params = [
            'or' => '90',
            'crop' => '280,380,20,10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(280, $image->width);
        $this->assertEquals(380, $image->height);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testLimitToImageBoundaries()
    {
        $testImage = $this->inputJpg;
        $params = [
            'crop' => '30000,30000,2405,1985'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
    }

    public function testNegativeWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'crop' => '-10,10,10,10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
    }

    public function testNegativeHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'crop' => '10,-10,10,10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
    }

    public function testBadExtractArea()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'crop' => '10,10,3000,10'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
    }

    public function testGetCrop()
    {
        $this->assertSame([0, 0], $this->manipulator->setParams(['a' => 'top-left'])->getCrop());
        $this->assertSame([0, 100], $this->manipulator->setParams(['a' => 'bottom-left'])->getCrop());
        $this->assertSame([0, 50], $this->manipulator->setParams(['a' => 'left'])->getCrop());
        $this->assertSame([100, 0], $this->manipulator->setParams(['a' => 'top-right'])->getCrop());
        $this->assertSame([100, 100], $this->manipulator->setParams(['a' => 'bottom-right'])->getCrop());
        $this->assertSame([100, 50], $this->manipulator->setParams(['a' => 'right'])->getCrop());
        $this->assertSame([50, 0], $this->manipulator->setParams(['a' => 'top'])->getCrop());
        $this->assertSame([50, 100], $this->manipulator->setParams(['a' => 'bottom'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'center'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'crop'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'center'])->getCrop());
        $this->assertSame([25, 75], $this->manipulator->setParams(['a' => 'crop-25-75'])->getCrop());
        $this->assertSame([0, 100], $this->manipulator->setParams(['a' => 'crop-0-100'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'crop-101-102'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'invalid'])->getCrop());
    }

    public function testResolveCropCoordinates()
    {
        $this->assertSame(
            [100, 100, 0, 0],
            $this->manipulator->setParams(['crop' => '100,100,0,0'])->resolveCropCoordinates(100, 100)
        );
        $this->assertSame(
            [101, 1, 1, 1],
            $this->manipulator->setParams(['crop' => '101,1,1,1'])->resolveCropCoordinates(100, 100)
        );
        $this->assertSame(
            [1, 101, 1, 1],
            $this->manipulator->setParams(['crop' => '1,101,1,1'])->resolveCropCoordinates(100, 100)
        );
        $this->assertNull($this->manipulator->setParams(['crop' => null])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,1,'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => ',1,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '-1,1,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,101,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,1,101'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => 'a'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => ''])->resolveCropCoordinates(100, 100));
    }
}
