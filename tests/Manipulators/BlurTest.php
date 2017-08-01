<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Blur;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;

class BlurTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Blur();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Blur::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = $this->getMockery(Image::class, function ($mock) {
            $mock->shouldReceive('gaussblur')->with('10')->andReturnSelf()->once();
        });

        $this->assertNotNull($image);
        $this->assertInstanceOf(Image::class, $this->manipulator->setParams(['blur' => 10])->run($image));
    }

    public function testGetBlur()
    {
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => '50'])->getBlur());
        $this->assertSame(50.0, $this->manipulator->setParams(['blur' => 50])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => null])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => 'a'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '-1'])->getBlur());
        $this->assertSame(-1.0, $this->manipulator->setParams(['blur' => '1001'])->getBlur());
    }
}
