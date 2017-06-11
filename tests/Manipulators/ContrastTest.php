<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContrastTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Contrast();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Contrast::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('format')
                ->andReturn(BandFormat::UCHAR)
                ->once();

            $mock->shouldReceive('maplut')
                ->with(Mockery::any())
                ->andReturnSelf()
                ->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams(['con' => 50])->run($image));
    }

    public function testGetContrast()
    {
        $this->assertSame(50, $this->manipulator->setParams(['con' => '50'])->getContrast());
        $this->assertSame(50, $this->manipulator->setParams(['con' => 50])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => null])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '-101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => 'a'])->getContrast());
    }
}
