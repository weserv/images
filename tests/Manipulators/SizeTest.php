<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use Mockery;

// TODO: Test t=fitup, t=square, t=squaredown, t=absolute, t=letterbox
class SizeTest extends \PHPUnit_Framework_TestCase
{
    private $manipulator;

    public function setUp()
    {
        $this->manipulator = new Size();
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\Size', $this->manipulator);
    }

    public function testSetMaxImageSize()
    {
        $this->manipulator->setMaxImageSize(500 * 500);
        $this->assertSame(500 * 500, $this->manipulator->getMaxImageSize());
    }

    public function testGetMaxImageSize()
    {
        $this->assertNull($this->manipulator->getMaxImageSize());
    }

    public function testRun()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(200, 200, 100, 100)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(200, 200, 100, 100)
                ->times(4);

            $mock->shouldReceive('shrinkv')
                ->with(2.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('shrinkh')
                ->with(2.0)
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 100,
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

    public function testGetWidth()
    {
        $this->assertSame(100, $this->manipulator->setParams(['w' => 100])->getWidth());
        $this->assertSame(100, $this->manipulator->setParams(['w' => 100.1])->getWidth());
        $this->assertSame(0, $this->manipulator->setParams(['w' => null])->getWidth());
        $this->assertSame(0, $this->manipulator->setParams(['w' => 'a'])->getWidth());
        $this->assertSame(0, $this->manipulator->setParams(['w' => '-100'])->getWidth());
    }

    public function testGetHeight()
    {
        $this->assertSame(100, $this->manipulator->setParams(['h' => 100])->getHeight());
        $this->assertSame(100, $this->manipulator->setParams(['h' => 100.1])->getHeight());
        $this->assertSame(0, $this->manipulator->setParams(['h' => null])->getHeight());
        $this->assertSame(0, $this->manipulator->setParams(['h' => 'a'])->getHeight());
        $this->assertSame(0, $this->manipulator->setParams(['h' => '-100'])->getHeight());
    }

    public function testGetFit()
    {
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'fit'])->getFit());
        $this->assertSame('fitup', $this->manipulator->setParams(['t' => 'fitup'])->getFit());
        $this->assertSame('square', $this->manipulator->setParams(['t' => 'square'])->getFit());
        $this->assertSame('squaredown', $this->manipulator->setParams(['t' => 'squaredown'])->getFit());
        $this->assertSame('absolute', $this->manipulator->setParams(['t' => 'absolute'])->getFit());
        $this->assertSame('letterbox', $this->manipulator->setParams(['t' => 'letterbox'])->getFit());
    }

    public function testGetCrop()
    {
        $this->assertSame([0, 0], $this->manipulator->setParams(['a' => 'top-left'])->getCrop());
        $this->assertSame([0, 100], $this->manipulator->setParams(['a' => 'bottom-left'])->getCrop());
        $this->assertSame([0, 50], $this->manipulator->setParams(['a' => 'left'])->getCrop());
        $this->assertSame([100, 0], $this->manipulator->setParams(['a' => 'top-right'])->getCrop());
        $this->assertSame([100, 100], $this->manipulator->setParams(['a' => 'bottom-right'])->getCrop());
        $this->assertSame([100, 50], $this->manipulator->setParams(['a' => 'right'])->getCrop());
        $this->assertSame([50, 0], $this->manipulator->setParams(['a' => 'top'])->getCrop());
        $this->assertSame([50, 100], $this->manipulator->setParams(['a' => 'bottom'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'center'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'crop'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'center'])->getCrop());
        $this->assertSame([25, 75], $this->manipulator->setParams(['a' => 'crop-25-75'])->getCrop());
        $this->assertSame([0, 100], $this->manipulator->setParams(['a' => 'crop-0-100'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'crop-101-102'])->getCrop());
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => 'invalid'])->getCrop());
    }

    public function testCheckImageSize()
    {
        $this->manipulator->setMaxImageSize(500 * 500);

        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1000)
                ->twice();

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(1000)
                ->twice();
        });

        $this->expectException(ImageTooLargeException::class);

        $this->manipulator->checkImageSize($image, 2000, 2000);
    }
}
