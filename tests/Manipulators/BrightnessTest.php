<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Brightness;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;

class BrightnessTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Brightness();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Brightness::class, $this->manipulator);
    }

    public function testGetBrightness()
    {
        $this->assertSame(50, $this->manipulator->setParams(['bri' => '50'])->getBrightness());
        $this->assertSame(50, $this->manipulator->setParams(['bri' => 50])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => null])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => '101'])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => '-101'])->getBrightness());
        $this->assertSame(0, $this->manipulator->setParams(['bri' => 'a'])->getBrightness());
    }
}
