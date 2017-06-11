<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class CropTest extends TestCase
{
    private $manipulator;
    private $image;

    public function setUp()
    {
        $this->manipulator = new Crop();
        $this->image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(100);
            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(100);
        });
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Crop::class, $this->manipulator);
    }

    public function testCrop()
    {
        $this->image->shouldReceive('crop')->with(0, 0, 100, 100)->andReturnSelf()->once();

        $this->assertInstanceOf(
            Image::class,
            $this->manipulator->setParams(['crop' => '100,100,0,0'])->run($this->image)
        );
    }

    public function testSquareCrop()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(327)
                ->times(1);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(369)
                ->times(1);

            $mock->shouldReceive('crop')
                ->with(0, 0, 300, 300)
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'square',
            'a' => 'top-left',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams($params)->run($image));
    }

    public function testEntropyCrop()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(327)
                ->times(1);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(369)
                ->times(1);

            $mock->shouldReceive('smartcrop')
                ->with(300, 300, ['interesting' => 'entropy'])
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'square',
            'a' => 'entropy',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams($params)->run($image));
    }

    public function testAttentionCrop()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(327)
                ->times(1);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(369)
                ->times(1);

            $mock->shouldReceive('smartcrop')
                ->with(300, 300, ['interesting' => 'attention'])
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'square',
            'a' => 'attention',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams($params)->run($image));
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

    public function testValidateCoordinates()
    {
        $this->assertSame([100, 100, 0, 0], $this->manipulator->limitToImageBoundaries($this->image, [100, 100, 0, 0]));
        $this->assertSame(
            [90, 90, 10, 10],
            $this->manipulator->limitToImageBoundaries($this->image, [100, 100, 10, 10])
        );
    }
}
