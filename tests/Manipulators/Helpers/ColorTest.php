<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function testThreeDigitColorCode()
    {
        $color = new Color('000');

        $this->assertSame([0, 0, 0, 255], $color->toRGBA());
    }

    public function testFourDigitColorCode()
    {
        $color = new Color('5000');

        $this->assertSame([0, 0, 0, 127.5], $color->toRGBA());
    }

    public function testSixDigitColorCode()
    {
        $color = new Color('000000');

        $this->assertSame([0, 0, 0, 255], $color->toRGBA());
    }

    public function testEightDigitColorCode()
    {
        $color = new Color('50000000');

        $this->assertSame([0, 0, 0, 127.5], $color->toRGBA());
    }

    public function testNamedColorCode()
    {
        $color = new Color('black');

        $this->assertSame([0, 0, 0, 255], $color->toRGBA());
    }

    public function testUnknownColor()
    {
        $color = new Color('unknown');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }
}
