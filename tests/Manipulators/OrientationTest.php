<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Angle;
use Jcupitt\Vips\Image;
use Mockery;
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
        $this->assertInstanceOf(Orientation::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('rot')->with(Angle::D90)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams([
            'rotation' => 0,
            'flip' => false,
            'flop' => false
        ])->run($image));

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams([
            'rotation' => 90,
            'flip' => false,
            'flop' => false
        ])->run($image));
    }
}
