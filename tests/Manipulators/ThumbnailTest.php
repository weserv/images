<?php

namespace Weserv\Images\Test\Manipulators;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;
use Mockery\MockInterface;
use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Manipulators\Thumbnail;
use Weserv\Images\Test\ImagesWeservTestCase;

class ThumbnailTest extends ImagesWeservTestCase
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
     * @var Thumbnail
     */
    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->manipulator = new Thumbnail();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Thumbnail::class, $this->manipulator);
    }

    public function testSetMaxImageSize()
    {
        $this->manipulator->setMaxImageSize(500 * 500);
        $this->assertSame(500 * 500, $this->manipulator->getMaxImageSize());
    }

    public function testGetMaxImageSize()
    {
        $this->assertNull($this->manipulator->getMaxImageSize());
    }

    public function testFit()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(294, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Provide only one dimension, should default to fit
     */
    public function testFixedWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testInvalidHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '-100'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Provide only one dimension, should default to fit
     */
    public function testFixedHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'h' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(392, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testInvalidWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '-100',
            'h' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(392, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testIdentityTransform()
    {
        $testImage = $this->inputJpg;
        $params = [];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testFitup()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '3000',
            't' => 'fitup'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2450, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testSquare()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testSquareUpscale()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '3000',
            't' => 'square'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2450, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Do not enlarge when input width is already less than output width
     */
    public function testSquareDownWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '2800',
            't' => 'squaredown'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Do not enlarge when input height is already less than output height
     */
    public function testSquareDownHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'h' => '2300',
            't' => 'squaredown'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testTiff()
    {
        $testImage = $this->inputTiff;
        $params = [
            'w' => '240',
            'h' => '320',
            't' => 'square'
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
     * Width or height considering ratio (portrait)
     */
    public function testTiffRatioPortrait()
    {
        $testImage = $this->inputTiff;
        $params = [
            'w' => '320',
            'h' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(243, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Width or height considering ratio (landscape)
     */
    public function testJpgRatioLandscape()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Downscale width and height, ignoring aspect ratio
     */
    public function testAbsoluteDownscale()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '320',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Downscale width, ignoring aspect ratio
     */
    public function testAbsoluteDownscaleWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Downscale width, ignoring aspect ratio
     */
    public function testAbsoluteDownscaleHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'h' => '320',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Upscale width and height, ignoring aspect ratio
     */
    public function testAbsoluteUpscale()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '3000',
            'h' => '3000',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3000, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Upscale width and height, ignoring aspect ratio
     */
    public function testAbsoluteUpscaleWidth()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '3000',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Upscale width and height, ignoring aspect ratio
     */
    public function testAbsoluteUpscaleHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'h' => '3000',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Downscale width, upscale height, ignoring aspect ratio
     */
    public function testAbsoluteDownscaleWidthUpscaleHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '3000',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(320, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * Upscale width, downscale height, ignoring aspect ratio
     */
    public function testAbsoluteUpscaleWidthDownscaleHeight()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '3000',
            'h' => '320',
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(3000, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testAbsoluteIdentityTransform()
    {
        $testImage = $this->inputJpg;
        $params = [
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * From CMYK to sRGB
     */
    public function testCMYKTosRGB()
    {
        $testImage = $this->inputJpgWithCmykProfile;
        $params = [
            'w' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(Interpretation::RGB, $image->interpretation);
        $this->assertEquals(320, $image->width);
    }

    /**
     * From profile-less CMYK to sRGB
     */
    public function testProfileLessCMYKTosRGB()
    {
        $testImage = $this->inputJpgWithCmykNoProfile;
        $expectedImage = $this->expectedDir . '/colourspace.cmyk-without-profile.jpg';
        $params = [
            'w' => '320'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        /** @var Image $image */
        $image = $this->api->run($uri, $params);

        $this->assertEquals(Interpretation::RGB, $image->interpretation);
        $this->assertEquals(320, $image->width);
        $this->assertSimilarImage($expectedImage, $image);
    }

    public function testGetFit()
    {
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'fit'])->getFit());
        $this->assertSame('fitup', $this->manipulator->setParams(['t' => 'fitup'])->getFit());
        $this->assertSame('square', $this->manipulator->setParams(['t' => 'square'])->getFit());
        $this->assertSame('squaredown', $this->manipulator->setParams(['t' => 'squaredown'])->getFit());
        $this->assertSame('absolute', $this->manipulator->setParams(['t' => 'absolute'])->getFit());
        $this->assertSame('letterbox', $this->manipulator->setParams(['t' => 'letterbox'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-center'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-25-75'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-0-100'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-101-102'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'invalid'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => null])->getFit());
    }

    public function testGetDpr()
    {
        $this->assertSame(1.0, $this->manipulator->setParams(['dpr' => 'invalid'])->getDpr());
        $this->assertSame(1.0, $this->manipulator->setParams(['dpr' => '-1'])->getDpr());
        $this->assertSame(1.0, $this->manipulator->setParams(['dpr' => '9'])->getDpr());
        $this->assertSame(2.0, $this->manipulator->setParams(['dpr' => '2'])->getDpr());
    }
}
