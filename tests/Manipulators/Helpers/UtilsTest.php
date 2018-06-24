<?php

namespace Weserv\Images\Test\Manipulators\Helpers;

use Weserv\Images\Manipulators\Helpers\Utils;
use Weserv\Images\Test\ImagesWeservTestCase;
use Jcupitt\Vips\Interpretation;

class UtilsTest extends ImagesWeservTestCase
{
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

    public function testResolveAngleRotation()
    {
        $this->assertSame(270, Utils::resolveAngleRotation('-3690'));
        $this->assertSame(270, Utils::resolveAngleRotation('-450'));
        $this->assertSame(270, Utils::resolveAngleRotation('-90'));
        $this->assertSame(90, Utils::resolveAngleRotation('90'));
        $this->assertSame(90, Utils::resolveAngleRotation('450'));
        $this->assertSame(90, Utils::resolveAngleRotation('3690'));
        $this->assertSame(180, Utils::resolveAngleRotation('-3780'));
        $this->assertSame(180, Utils::resolveAngleRotation('-540'));
        $this->assertSame(0, Utils::resolveAngleRotation('0'));
        $this->assertSame(180, Utils::resolveAngleRotation('180'));
        $this->assertSame(180, Utils::resolveAngleRotation('540'));
        $this->assertSame(180, Utils::resolveAngleRotation('3780'));
        $this->assertSame(0, Utils::resolveAngleRotation('invalid'));
        $this->assertSame(0, Utils::resolveAngleRotation('91'));
    }

    public function testDetermineImageExtension()
    {
        $this->assertSame('jpg', Utils::determineImageExtension('jpegload'));
        $this->assertSame('png', Utils::determineImageExtension('pngload'));
        $this->assertSame('webp', Utils::determineImageExtension('webpload'));
        $this->assertSame('tiff', Utils::determineImageExtension('tiffload'));
        $this->assertSame('gif', Utils::determineImageExtension('gifload'));
        $this->assertSame('unknown', Utils::determineImageExtension('invalid'));
    }

    public function testFormatBytes()
    {
        $base = 1024;
        $pow2 = $base ** 2;
        $pow3 = $base ** 3;
        $pow4 = $base ** 4;

        $this->assertSame('0 B', Utils::formatBytes(0));
        $this->assertSame('1 B', Utils::formatBytes(1));
        $this->assertSame('1023 B', Utils::formatBytes($base - 1));
        $this->assertSame('1 KB', Utils::formatBytes($base));
        $this->assertSame('1024 KB', Utils::formatBytes($pow2 - 1));
        $this->assertSame('1 MB', Utils::formatBytes($pow2));
        $this->assertSame('1024 MB', Utils::formatBytes($pow3 - 1));
        $this->assertSame('1 GB', Utils::formatBytes($pow3));
        $this->assertSame('1024 GB', Utils::formatBytes($pow4 - 1));
        $this->assertSame('1 TB', Utils::formatBytes($pow4));
        $this->assertSame('203.25 MB', Utils::formatBytes(213123123));
        $this->assertSame('19.85 GB', Utils::formatBytes(21312312390));
    }
}
