<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Interpretation;
use Jcupitt\Vips\Kernel;
use Mockery;
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 245)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967)
                ->times(3);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421)
                ->times(3);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
                ->andReturn(0)
                ->once();

            $mock->shouldReceive('affine')
                ->with(Mockery::any(), Mockery::any())
                ->andReturnSelf()
                ->once();
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 327)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 403)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967)
                ->times(3);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421)
                ->times(3);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 327)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
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
        $image = Mockery::mock('Jcupitt\Vips\Image[__get, typeof]', [''], function ($mock) {
            $mock->shouldReceive('__get')
                ->with('width')
                ->andReturn(1967, 1967, 1967, 245)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('height')
                ->andReturn(2421, 2421, 2421, 302)
                ->times(4);

            $mock->shouldReceive('__get')
                ->with('interpretation')
                ->andReturn(Interpretation::SRGB)
                ->once();

            $mock->shouldReceive('typeof')
                ->with(Utils::VIPS_META_ICC_NAME)
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
        $this->assertSame('fit', $this->manipulator->setParams(['t' => 'invalid'])->getFit());
        $this->assertSame('fit', $this->manipulator->setParams(['t' => null])->getFit());
    }

    public function testCheckImageSize()
    {
        $this->manipulator->setMaxImageSize(500 * 500);

        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [''], function ($mock) {
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
