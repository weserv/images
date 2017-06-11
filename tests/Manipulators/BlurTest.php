<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class BlurTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Blur();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Blur::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
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
