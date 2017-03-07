<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Kernel;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SizeTest extends TestCase
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

    public function testFit()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 245, 244)
                ->times(5);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302)
                ->times(4);

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('shrinkv')
                ->with(8.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('shrinkh')
                ->with(8.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reducev')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reduceh')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'fit',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }

    public function testFitup()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 2437)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421)
                ->times(3);

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('affine')
                ->with(Mockery::any()/*, ['interpolate' => 'bicubic']*/)
                ->andReturnSelf()
                ->twice();
        });

        $params = [
            'w' => 3000,
            'h' => 3000,
            't' => 'fitup',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }

    public function testSquare()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 327, 327, 327, 327, 327)
                ->times(8);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 403, 369, 369, 369)
                ->times(7);

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('shrinkv')
                ->with(6.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('shrinkh')
                ->with(6.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reducev')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reduceh')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('extract_area')
                ->with(0, 0, 300, 300)
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'square',
            'a' => 'top-left',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }

    public function testSquareDown()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421)
                ->times(4);

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();
        });

        $params = [
            'w' => 3000,
            'h' => 3000,
            't' => 'squaredown',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }


    public function testAbsolute()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 327, 300)
                ->times(5);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302, 300)
                ->times(5);

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('shrinkv')
                ->with(8.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('shrinkh')
                ->with(6.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reducev')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reduceh')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();
        });

        $params = [
            'w' => 300,
            'h' => 300,
            't' => 'absolute',
            'hasAlpha' => false,
            'is16Bit' => false,
            'isPremultiplied' => false,
        ];

        $this->assertInstanceOf(
            'Jcupitt\Vips\Image',
            $this->manipulator->setParams($params)->run($image)
        );
    }

    public function testLetterbox()
    {
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function (MockInterface $mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 245, 244, 244)
                ->times(6);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302, 300)
                ->times(5);

            $mock->shouldReceive('__get')
                ->with('bands')
                ->andReturn(3)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_LOADER)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('shrinkv')
                ->with(8.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('shrinkh')
                ->with(8.0)
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reducev')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
                ->once();

            $mock->shouldReceive('reduceh')
                ->with(Mockery::any(), [
                    'kernel' => Kernel::LANCZOS3,
                    'centre' => true,
                ])
                ->andReturnSelf()
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
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-left'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-right'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-top'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-bottom'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-center'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-25-75'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-0-100'])->getFit());
        $this->assertSame('crop', $this->manipulator->setParams(['t' => 'crop-101-102'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'crop-center-left'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'crop-left-left'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'crop-right-left'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'crop-bottom-right-invalid'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'invalid'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => null])->getFit());
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
        $this->assertSame([50, 50], $this->manipulator->setParams(['a' => null])->getCrop());
    }

    public function testCheckImageSize()
    {
        $this->manipulator->setMaxImageSize(500 * 500);

        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function (MockInterface $mock) {
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
