<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

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

    public function testMapToRange()
    {
        $this->assertSame(127.5, Utils::mapToRange(50, 0, 100, 0, 255));
    }
}
