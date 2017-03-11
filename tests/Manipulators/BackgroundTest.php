<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Mockery;
use PHPUnit\Framework\TestCase;

class BackgroundTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Background();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Background', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
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

        $this->assertInstanceOf('Jcupitt\Vips\Image', $this->manipulator->setParams($params)->run($image));
    }
}
