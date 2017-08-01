<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Orientation;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Angle;
use Jcupitt\Vips\Image;

class OrientationTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Orientation();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Orientation::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = $this->getMockery(Image::class, function ($mock) {
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
