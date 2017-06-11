<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;
use Mockery;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testIs16Bit()
    {
        $this->assertTrue(Utils::is16Bit(Interpretation::RGB16));
        $this->assertTrue(Utils::is16Bit(Interpretation::GREY16));
        $this->assertFalse(Utils::is16Bit(Interpretation::SRGB));
    }

    public function testMaximumImageAlpha()
    {
        $this->assertSame(65535, Utils::maximumImageAlpha(Interpretation::RGB16));
        $this->assertSame(65535, Utils::maximumImageAlpha(Interpretation::GREY16));
        $this->assertSame(255, Utils::maximumImageAlpha(Interpretation::SRGB));
    }

    public function testExifOrientation()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
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
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('typeof')->with(Utils::VIPS_META_ORIENTATION)->andReturn(1, 0)->twice();
            $mock->shouldReceive('get')->with(Utils::VIPS_META_ORIENTATION)->andReturn(6)->once();
            $mock->shouldReceive('remove')->with(Utils::VIPS_META_ORIENTATION)->once();
        });

        // Rotate 90 degrees EXIF orientation; auto rotate
        $this->assertSame([90, false, false], Utils::calculateRotationAndFlip(['or' => '-1'], $image));

        // No EXIF Orientation + user wants to rotate it 90 degrees
        $this->assertSame([90, false, false], Utils::calculateRotationAndFlip(['or' => '90'], $image));
    }

    public function testMapToRange()
    {
        $this->assertSame(127.5, Utils::mapToRange(50, 0, 100, 0, 255));
    }
}
