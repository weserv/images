<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Filter;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

class FilterTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Filter();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Filter::class, $this->manipulator);
    }

    public function testRunGreyscaleFilter()
    {
        $image = $this->getMockery(Image::class, function ($mock) {
            $mock->shouldReceive('colourspace')->with(Interpretation::B_W)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runGreyscaleFilter($image));
    }

    public function testRunSepiaFilter()
    {
        $image = $this->getMockery(Image::class, function ($mock) {
            $mock->shouldReceive('recomb')->with(\Mockery::any())->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runSepiaFilter($image));
    }

    public function testRunNegateFilter()
    {
        $image = $this->getMockery(Image::class, function ($mock) {
            $mock->shouldReceive('invert')->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->runNegateFilter($image));
    }
}
