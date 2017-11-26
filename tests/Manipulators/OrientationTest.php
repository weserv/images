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
            $mock->shouldReceive('copyMemory')->andReturnSelf()->once();
            $mock->shouldReceive('rot')->with(Angle::D90)->andReturnSelf()->once();
        });

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams([
            'or' => '0'
        ])->run($image));

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams([
            'or' => '90'
        ])->run($image));
    }
}
