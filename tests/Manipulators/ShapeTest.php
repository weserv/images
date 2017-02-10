<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Interpretation;
use Mockery;
use Mockery\MockInterface;

class ShapeTest extends \PHPUnit_Framework_TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Shape();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Shape', $this->manipulator);
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[bandjoin, __get]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->twice();

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(100)
                ->once();

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(100)
                ->once();

            $mock->shouldReceive('__get')
                ->with('format')
                ->andReturn(BandFormat::UCHAR)
                ->once();

            $mock->shouldReceive('extract_band')
                ->with(Mockery::any(), Mockery::any())
                ->andReturnSelf()
                ->twice();

            $mock->shouldReceive('linear')
                ->with(Mockery::any(), 0, Mockery::any())
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('bandjoin')
                ->with(Mockery::any())
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'shape' => 'circle',
            'accessMethod' => Access::SEQUENTIAL,
            'hasAlpha' => true,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
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
        $this->assertSame(null, $this->manipulator->setParams(['shape' => null])->getShape());
        $this->assertSame(null, $this->manipulator->setParams(['shape' => 'a'])->getShape());
        $this->assertSame(null, $this->manipulator->setParams(['shape' => '-1'])->getShape());
        $this->assertSame(null, $this->manipulator->setParams(['shape' => '100'])->getShape());
    }
}
