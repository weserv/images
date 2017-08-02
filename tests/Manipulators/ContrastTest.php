<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Contrast;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;

class ContrastTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Contrast();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Contrast::class, $this->manipulator);
    }

    public function testGetContrast()
    {
        $this->assertSame(50, $this->manipulator->setParams(['con' => '50'])->getContrast());
        $this->assertSame(50, $this->manipulator->setParams(['con' => 50])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => null])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => '-101'])->getContrast());
        $this->assertSame(0, $this->manipulator->setParams(['con' => 'a'])->getContrast());
    }
}
