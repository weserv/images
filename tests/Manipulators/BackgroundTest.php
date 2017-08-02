<?php

namespace AndriesLouw\imagesweserv\Test\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Background;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Jcupitt\Vips\Image;

class BackgroundTest extends ImagesweservTestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Background();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Background::class, $this->manipulator);
    }
}
