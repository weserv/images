<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Angle;
use Mockery;
use Mockery\MockInterface;

class OrientationTest extends \PHPUnit_Framework_TestCase
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
            $mock->shouldReceive('typeof')->with(Utils::VIPS_META_ORIENTATION)->andReturn(1, 0)->twice();
            $mock->shouldReceive('get')->with(Utils::VIPS_META_ORIENTATION)->andReturn(6)->once();
            $mock->shouldReceive('remove')->with(Utils::VIPS_META_ORIENTATION)->once();
            $mock->shouldReceive('rot')->with(Angle::D90)->andReturnSelf()->twice();
        });

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams(['or' => 'auto'])->run($image)
        );

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams(['or' => '90'])->run($image)
        );
    }

    public function testGetOrientation()
    {
        $this->assertSame(-1, $this->manipulator->setParams(['or' => 'auto'])->getOrientation());
        $this->assertSame(-1, $this->manipulator->setParams(['or' => '0'])->getOrientation());
        $this->assertSame(90, $this->manipulator->setParams(['or' => '90'])->getOrientation());
        $this->assertSame(180, $this->manipulator->setParams(['or' => '180'])->getOrientation());
        $this->assertSame(270, $this->manipulator->setParams(['or' => '270'])->getOrientation());
        $this->assertSame(-1, $this->manipulator->setParams(['or' => null])->getOrientation());
        $this->assertSame(-1, $this->manipulator->setParams(['or' => '1'])->getOrientation());
        $this->assertSame(-1, $this->manipulator->setParams(['or' => '45'])->getOrientation());
    }
}
