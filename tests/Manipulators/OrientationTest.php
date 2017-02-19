<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Angle;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class OrientationTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Orientation();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Orientation', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image', function (MockInterface $mock) {
            $mock->shouldReceive('rot')->with(Angle::D90)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams([
                'rotation' => 0,
                'flip' => false,
                'flop' => false
            ])->run($image)
        );

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams([
                'rotation' => 90,
                'flip' => false,
                'flop' => false
            ])->run($image)
        );
    }
}
