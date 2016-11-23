<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Mockery;

class TrimTest extends \PHPUnit_Framework_TestCase
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
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Trim', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[more, __get]', [''], function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->times(10);

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
                ->andReturn([0])
                ->once();

            $mock->shouldReceive('rank')
                ->with(Mockery::any(), Mockery::any(), Mockery::any())
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('linear')
                ->with(1, [0], [])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('abs')
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('more')
                ->with(25.5)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('project')
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('profile')
                ->andReturnSelf()
                ->times(4);

            $mock->shouldReceive('flip')
                ->andReturnSelf()
                ->twice();

            $mock->shouldReceive('min')
                ->andReturn(0)
                ->times(4);

            $mock->shouldReceive('extract_band')
                ->with(Mockery::any())
                ->andReturnSelf();

            $mock->shouldReceive('crop')
                ->with(0, 0, 100, 100)
                ->andReturnSelf();
        });

        $params = [
            'trim' => '10',
            'hasAlpha' => false,
            'is16Bit' => false,
            'maxAlpha' => 255,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }

    public function testGetTrim()
    {
        $this->assertSame(50, $this->manipulator->setParams(['trim' => '50'])->getTrim());
        $this->assertSame(50, $this->manipulator->setParams(['trim' => 50.50])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => null])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => 'a'])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => '-1'])->getTrim());
        $this->assertSame(10, $this->manipulator->setParams(['trim' => '101'])->getTrim());
    }
}
