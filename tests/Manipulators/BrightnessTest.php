<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class BrightnessTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Brightness();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Brightness::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('linear')->with([1, 1, 1], [127.5, 127.5, 127.5])->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams(['bri' => 50])->run($image));
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
