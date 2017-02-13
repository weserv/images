<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CropTest extends TestCase
{
    private $manipulator;
    private $image;

    public function setUp()
    {
        $this->manipulator = new Crop();
        $this->image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(100);
            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(100);
        });
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Crop', $this->manipulator);
    }

    public function testRun()
    {
        $this->image->shouldReceive('crop')->with(0, 0, 100, 100)->andReturnSelf()->once();

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams(['crop' => '100,100,0,0'])->run($this->image)
        );
    }

    public function testGetCoordinates()
    {
        $this->assertSame(
            [100, 100, 0, 0],
            $this->manipulator->setParams(['crop' => '100,100,0,0'])->getCoordinates($this->image)
        );
        $this->assertSame(
            [101, 1, 1, 1],
            $this->manipulator->setParams(['crop' => '101,1,1,1'])->getCoordinates($this->image)
        );
        $this->assertSame(
            [1, 101, 1, 1],
            $this->manipulator->setParams(['crop' => '1,101,1,1'])->getCoordinates($this->image)
        );
        $this->assertSame(null, $this->manipulator->setParams(['crop' => null])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '1,1,1,'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '1,1,,1'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '1,,1,1'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => ',1,1,1'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '-1,1,1,1'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '1,1,101,1'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => '1,1,1,101'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => 'a'])->getCoordinates($this->image));
        $this->assertSame(null, $this->manipulator->setParams(['crop' => ''])->getCoordinates($this->image));
    }

    public function testValidateCoordinates()
    {
        $this->assertSame([100, 100, 0, 0], $this->manipulator->limitToImageBoundaries($this->image, [100, 100, 0, 0]));
        $this->assertSame(
            [90, 90, 10, 10],
            $this->manipulator->limitToImageBoundaries($this->image, [100, 100, 10, 10])
        );
    }
}
