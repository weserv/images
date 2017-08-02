<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Filter;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

class FilterTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Filter();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Filter::class, $this->manipulator);
    }

}
