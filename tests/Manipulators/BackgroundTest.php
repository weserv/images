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

    public function testRun()
    {
        $image = $this->getMockery('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->twice();

            $mock->shouldReceive('flatten')
                ->with([
                    'background' => [
                        0,
                        0,
                        0,
                    ]
                ])
                ->andReturnSelf()
                ->once();
        });

        $this->assertNotNull($image);

        $params = [
            'bg' => 'black',
            'hasAlpha' => true,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams($params)->run($image));
    }
}
