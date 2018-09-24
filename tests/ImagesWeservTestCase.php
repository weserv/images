<?php

namespace Weserv\Images\Test;

use Jcupitt\Vips\Image;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Weserv\Images\Manipulators\Background;
use Weserv\Images\Manipulators\Blur;
use Weserv\Images\Manipulators\Brightness;
use Weserv\Images\Manipulators\Contrast;
use Weserv\Images\Manipulators\Crop;
use Weserv\Images\Manipulators\Filter;
use Weserv\Images\Manipulators\Gamma;
use Weserv\Images\Manipulators\Letterbox;
use Weserv\Images\Manipulators\ManipulatorInterface;
use Weserv\Images\Manipulators\Orientation;
use Weserv\Images\Manipulators\Shape;
use Weserv\Images\Manipulators\Sharpen;
use Weserv\Images\Manipulators\Thumbnail;
use Weserv\Images\Manipulators\Trim;

/**
 * A base test case with some custom expectations.
 *
 * @requires extension vips
 */
class ImagesWeservTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;
    use FixturesTrait;

    /**
     * Verify similarity of expected vs actual image
     *
     * @param Image|string $expectedImage
     * @param Image|string $actualImage
     * @param int $threshold
     *
     * @throws \Jcupitt\Vips\Exception
     * @throws ExpectationFailedException
     *
     * @return void
     */
    public function assertSimilarImage($expectedImage, $actualImage, int $threshold = 5): void
    {
        $constraint = new SimilarImageConstraint($expectedImage, $threshold);
        self::assertThat($actualImage, $constraint);
    }

    /**
     * Verify the maximum color distance
     *
     * @param Image|string $expectedImage
     * @param Image|string $actualImage
     * @param float $threshold
     *
     * @throws \Jcupitt\Vips\Exception
     * @throws ExpectationFailedException
     *
     * @return void
     */
    public function assertMaxColorDistance($expectedImage, $actualImage, float $threshold = 1.0): void
    {
        $constraint = new MaxColorDistanceConstraint($expectedImage, $threshold);
        self::assertThat($actualImage, $constraint);
    }

    /**
     * @return ManipulatorInterface[] Collection of manipulators.
     */
    public function getManipulators(): array
    {
        return [
            new Trim(),
            new Thumbnail(71000000),
            new Orientation(),
            new Crop(),
            new Letterbox(),
            new Shape,
            new Brightness(),
            new Contrast(),
            new Gamma(),
            new Sharpen(),
            new Filter(),
            new Blur(),
            new Background(),
        ];
    }

    protected function tearDown()
    {
        \Mockery::close();
    }

    /**
     * Shortcut to \Mockery::mock().
     *
     * @param mixed $class Class to mock.
     *
     * @return \Mockery\MockInterface
     */
    protected function getMockery($class): MockInterface
    {
        return \Mockery::mock($class);
    }
}
