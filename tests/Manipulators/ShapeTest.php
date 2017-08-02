<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Shape;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

class ShapeTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Shape();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Shape::class, $this->manipulator);
    }

    public function testGetShape()
    {
        $this->assertSame('circle', $this->manipulator->setParams(['shape' => 'circle'])->getShape());
        $this->assertSame('ellipse', $this->manipulator->setParams(['shape' => 'ellipse'])->getShape());
        $this->assertSame('hexagon', $this->manipulator->setParams(['shape' => 'hexagon'])->getShape());
        $this->assertSame('pentagon', $this->manipulator->setParams(['shape' => 'pentagon'])->getShape());
        $this->assertSame('pentagon-180', $this->manipulator->setParams(['shape' => 'pentagon-180'])->getShape());
        $this->assertSame('square', $this->manipulator->setParams(['shape' => 'square'])->getShape());
        $this->assertSame('star', $this->manipulator->setParams(['shape' => 'star'])->getShape());
        $this->assertSame('triangle', $this->manipulator->setParams(['shape' => 'triangle'])->getShape());
        $this->assertSame('triangle-180', $this->manipulator->setParams(['shape' => 'triangle-180'])->getShape());
        $this->assertSame('circle', $this->manipulator->setParams(['circle' => true])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => null])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => 'a'])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => '-1'])->getShape());
        $this->assertNull($this->manipulator->setParams(['shape' => '100'])->getShape());
    }
}
