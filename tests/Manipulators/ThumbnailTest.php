<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Thumbnail;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;

class ThumbnailTest extends ImagesweservTestCase
{
    private $client;
    private $api;

    private $manipulator;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, null, $this->getManipulators());
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
            'h' => '240',
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(294, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(392, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    public function testIdentityTransform()
    {
        $testImage = $this->inputJpg;
        $params = [];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2450, $image->height);
        $this->assertFalse($hasAlpha);
    }

    public function testTooLarge()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '35500000',
            'h' => '35500000'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        $this->expectException(ImageTooLargeException::class);

        $this->api->run($uri, $params);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2450, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('tiff', $extension);
        $this->assertEquals(240, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('tiff', $extension);
        $this->assertEquals(243, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3000, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3000, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(3000, $image->height);
        $this->assertFalse($hasAlpha);
    }

    /*
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3000, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
    }

    public function testAbsoluteIdentityTransform()
    {
        $testImage = $this->inputJpg;
        $params = [
            't' => 'absolute'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(2725, $image->width);
        $this->assertEquals(2225, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3, $image->bands);
        $this->assertEquals(80, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('png', $extension);
        $this->assertEquals(4, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(80, $image->height);
        $this->assertTrue($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('jpg', $extension);
        $this->assertEquals(3, $image->bands);
        $this->assertEquals(80, $image->width);
        $this->assertEquals(320, $image->height);
        $this->assertFalse($hasAlpha);
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

        list($image, $extension, $hasAlpha) = $this->api->run($uri, $params);

        $this->assertEquals('png', $extension);
        $this->assertEquals(4, $image->bands);
        $this->assertEquals(320, $image->width);
        $this->assertEquals(80, $image->height);
        $this->assertTrue($hasAlpha);
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
}