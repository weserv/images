<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators\Helpers;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Interpretation;

class UtilsTest extends ImagesweservTestCase
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
}
