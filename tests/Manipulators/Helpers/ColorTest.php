<?php

namespace Weserv\Images\Test\Manipulators\Helpers;

use Weserv\Images\Manipulators\Helpers\Color;
use Weserv\Images\Test\ImagesWeservTestCase;

class ColorTest extends ImagesWeservTestCase
{
    public function testThreeDigitColorCode(): void
    {
        $color = new Color('ABC');

        $this->assertSame([170, 187, 204, 255], $color->toRGBA());
    }

    public function testThreeDigitWithHash(): void
    {
        $color = new Color('#ABC');

        $this->assertSame([170, 187, 204, 255], $color->toRGBA());
    }

    public function testFourDigitColorCode(): void
    {
        $color = new Color('0ABC');

        $this->assertSame([170, 187, 204, 0], $color->toRGBA());
    }

    public function testFourDigitColorCodeWithHash(): void
    {
        $color = new Color('#0ABC');

        $this->assertSame([170, 187, 204, 0], $color->toRGBA());
    }

    public function testSixDigitColorCode(): void
    {
        $color = new Color('11FF33');

        $this->assertSame([17, 255, 51, 255], $color->toRGBA());
    }

    public function testSixDigitColorCodeWithHash(): void
    {
        $color = new Color('#11FF33');

        $this->assertSame([17, 255, 51, 255], $color->toRGBA());
    }

    public function testEightDigitColorCode(): void
    {
        $color = new Color('0011FF33');

        $this->assertSame([17, 255, 51, 0], $color->toRGBA());
    }

    public function testEightDigitColorCodeWithHash(): void
    {
        $color = new Color('#0011FF33');

        $this->assertSame([17, 255, 51, 0], $color->toRGBA());
    }

    public function testNamedColorCode(): void
    {
        $color = new Color('black');

        $this->assertSame([0, 0, 0, 255], $color->toRGBA());
    }

    public function testAllNonHexColor(): void
    {
        $color = new Color('ZXCZXCMM');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testOneNonHexColor(): void
    {
        $color = new Color('0123456X');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testTwoDigitColorCode(): void
    {
        $color = new Color('01');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testFiveDigitColorCode(): void
    {
        $color = new Color('01234');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testNineDigitColorCode(): void
    {
        $color = new Color('012345678');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testNullColor(): void
    {
        $color = new Color(null);

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }

    public function testUnknownColor(): void
    {
        $color = new Color('unknown');

        $this->assertSame([0, 0, 0, 0], $color->toRGBA());
    }
}
