<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Interpretation;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testIs16Bit()
    {
        $this->assertSame(true, Utils::is16Bit(Interpretation::RGB16));
        $this->assertSame(true, Utils::is16Bit(Interpretation::GREY16));
        $this->assertSame(false, Utils::is16Bit(Interpretation::SRGB));
    }

    public function testMaximumImageAlpha()
    {
        $this->assertSame(65535, Utils::maximumImageAlpha(Interpretation::RGB16));
        $this->assertSame(65535, Utils::maximumImageAlpha(Interpretation::GREY16));
        $this->assertSame(255, Utils::maximumImageAlpha(Interpretation::SRGB));
    }

    public function testHasAlpha()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(4)
                ->once();
        });

        $this->assertSame(true, Utils::hasAlpha($image));
    }

    public function testExifOrientation()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (MockInterface $mock) {
            $mock->shouldReceive('typeof')->with(Utils::VIPS_META_ORIENTATION)->andReturn(1, 0)->twice();
            $mock->shouldReceive('get')->with(Utils::VIPS_META_ORIENTATION)->andReturn(6)->once();
        });

        // Rotate 90 degrees EXIF orientation
        $this->assertSame(6, Utils::exifOrientation($image));

        // No EXIF orientation
        $this->assertSame(0, Utils::exifOrientation($image));
    }

    public function testCalculateRotationAndFlip()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (MockInterface $mock) {
            $mock->shouldReceive('typeof')->with(Utils::VIPS_META_ORIENTATION)->andReturn(1, 0)->twice();
            $mock->shouldReceive('get')->with(Utils::VIPS_META_ORIENTATION)->andReturn(6)->once();
            $mock->shouldReceive('remove')->with(Utils::VIPS_META_ORIENTATION)->once();
        });

        // Rotate 90 degrees EXIF orientation; auto rotate
        $this->assertSame([90, false, false], Utils::calculateRotationAndFlip(-1, $image));

        // No EXIF Orientation + user wants to rotate it 90 degrees
        $this->assertSame([90, false, false], Utils::calculateRotationAndFlip(90, $image));
    }

    public function testMapToRange()
    {
        $this->assertSame(127.5, Utils::mapToRange(50, 0, 100, 0, 255));
    }
}
