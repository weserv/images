<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Crop;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;

class CropTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Crop();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Crop::class, $this->manipulator);
    }

    public function testResolveCropCoordinates()
    {
        $this->assertSame(
            [100, 100, 0, 0],
            $this->manipulator->setParams(['crop' => '100,100,0,0'])->resolveCropCoordinates(100, 100)
        );
        $this->assertSame(
            [101, 1, 1, 1],
            $this->manipulator->setParams(['crop' => '101,1,1,1'])->resolveCropCoordinates(100, 100)
        );
        $this->assertSame(
            [1, 101, 1, 1],
            $this->manipulator->setParams(['crop' => '1,101,1,1'])->resolveCropCoordinates(100, 100)
        );
        $this->assertNull($this->manipulator->setParams(['crop' => null])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,1,'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => ',1,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '-1,1,1,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,101,1'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => '1,1,1,101'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => 'a'])->resolveCropCoordinates(100, 100));
        $this->assertNull($this->manipulator->setParams(['crop' => ''])->resolveCropCoordinates(100, 100));
    }

}
