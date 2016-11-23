<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Interpretation;
use Mockery;

class FilterTest extends \PHPUnit_Framework_TestCase
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
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Filter', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('colourspace')->with(Interpretation::B_W)->andReturnSelf()->once();
            $mock->shouldReceive('recomb')->with(Mockery::any())->andReturnSelf()->once();
        });

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams(['filt' => 'greyscale'])->run($image)
        );

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams(['filt' => 'sepia'])->run($image)
        );

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams([])->run($image)
        );
    }

    public function testRunGreyscaleFilter()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('colourspace')->with(Interpretation::B_W)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->runGreyscaleFilter($image)
        );
    }

    public function testRunSepiaFilter()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('recomb')->with(Mockery::any())->andReturnSelf()->once();
        });

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->runSepiaFilter($image)
        );
    }
}
