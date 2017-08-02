<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Gamma;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;

class GammaTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Gamma();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Gamma::class, $this->manipulator);
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
