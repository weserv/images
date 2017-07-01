<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;
use Mockery;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Filter();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Filter::class, $this->manipulator);
    }

    public function testRunGreyscaleFilter()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('colourspace')->with(Interpretation::B_W)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runGreyscaleFilter($image));
    }

    public function testRunSepiaFilter()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('recomb')->with(Mockery::any())->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runSepiaFilter($image));
    }

    public function testRunNegateFilter()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('invert')->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runNegateFilter($image));
    }
}
