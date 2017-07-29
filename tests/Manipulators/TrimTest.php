<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class TrimTest extends TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Trim();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Trim::class, $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[more, __get]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(100)
                ->once();

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(100)
                ->once();

            $mock->shouldReceive('getpoint')
                ->with(0, 0)
                ->andReturn([255.0, 255.0, 255.0])
                ->once();

            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->once();

            $mock->shouldReceive('find_trim')
                ->with([
                    'threshold' => 10,
                    'background' => [255.0, 255.0]
                ])
                ->andReturn([
                    'left' => 0,
                    'top' => 0,
                    'width' => 100,
                    'height' => 100
                ])
                ->once();

            $mock->shouldReceive('crop')
                ->with(0, 0, 100, 100)
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'trim' => '10',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(Image::class, $this->manipulator->setParams($params)->run($image));
    }

    public function testGetTrim()
    {
        $this->assertSame(50, $this->manipulator->setParams(['trim' => '50'])->getTrim());
        $this->assertSame(50, $this->manipulator->setParams(['trim' => 50.50])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => null])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => 'a'])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => '-1'])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => '256'])->getTrim());
    }
}
