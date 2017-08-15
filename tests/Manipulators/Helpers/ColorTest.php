<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators\Helpers;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;

class ColorTest extends ImagesweservTestCase
{
    public function testThreeDigitColorCode()
    {
        $color = new Color('ABC');

        $this->assertSame([170, 187, 204, 255], $color->toRGBA());
    }

    public function testThreeDigitWithHash()
    {
        $color = new Color('#ABC');

        $this->assertSame([170, 187, 204, 255], $color->toRGBA());
    }

    public function testFourDigitColorCode()
    {
        $color = new Color('0ABC');

        $this->assertSame([170, 187, 204, 0], $color->toRGBA());
    }

    public function testFourDigitColorCodeWithHash()
    {
        $color = new Color('#0ABC');

        $this->assertSame([170, 187, 204, 0], $color->toRGBA());
    }

    public function testSixDigitColorCode()
    {
        $color = new Color('11FF33');

        $this->assertSame([17, 255, 51, 255], $color->toRGBA());
    }

    public function testSixDigitColorCodeWithHash()
    {
        $color = new Color('#11FF33');

        $this->assertSame([17, 255, 51, 255], $color->toRGBA());
    }

    public function testEightDigitColorCode()
    {
        $color = new Color('0011FF33');

        $this->assertSame([17, 255, 51, 0], $color->toRGBA());
    }

    public function testEightDigitColorCodeWithHash()
    {
        $color = new Color('#0011FF33');

        $this->assertSame([17, 255, 51, 0], $color->toRGBA());
    }

    public function testNamedColorCode()
    {
        $color = new Color('black');

        $this->assertSame([0, 0, 0, 255], $color->toRGBA());
    }

    public function testAllNonHexColor()
    {
        $color = new Color('ZXCZXCMM');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testOneNonHexColor()
    {
        $color = new Color('0123456X');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testNullColor()
    {
        $color = new Color(null);

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testObjectColor()
    {
        $color = new Color(new \stdClass());

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testUnknownColor()
    {
        $color = new Color('unknown');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }
}
