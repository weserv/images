<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class GammaTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Gamma();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Gamma::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock(Image::class, function ($mock) {
            $mock->shouldReceive('gamma')->with(['exponent' => 1.5])->andReturnSelf()->once();
        });

        $this->assertInstanceOf(
            Image::class,
            $this->manipulator->setParams(['hasAlpha' => false, 'gam' => '1.5'])->run($image)
        );
    }

    public function testGetGamma()
    {
        $this->assertSame(1.5, $this->manipulator->setParams(['gam' => '1.5'])->getGamma());
        $this->assertSame(1.5, $this->manipulator->setParams(['gam' => 1.5])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => null])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => 'a'])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => '.1'])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => '3.999'])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => '0.005'])->getGamma());
        $this->assertSame(2.2, $this->manipulator->setParams(['gam' => '-1'])->getGamma());
    }
}
