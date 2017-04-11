<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Extend;
use Mockery;
use PHPUnit\Framework\TestCase;

class LetterboxTest extends TestCase
{
    private $manipulator;
    private $image;

    public function setUp()
    {
        $this->manipulator = new Letterbox();
        $this->image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(100);
            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(100);
        });
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Letterbox', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(244)
                ->times(2);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(300)
                ->times(1);

            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->once();

            $mock->shouldReceive('embed')
                ->with(
                    (300 - 244) / 2,
                    0,
                    300,
                    300,
                    ['extend' => Extend::BACKGROUND, 'background' => [0, 0, 0]]
                )
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'letterbox',
            'bg' => 'black',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }
}
