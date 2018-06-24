<?php

namespace Weserv\Images\Test;

use Weserv\Images\Manipulators\Background;
use Weserv\Images\Manipulators\Blur;
use Weserv\Images\Manipulators\Brightness;
use Weserv\Images\Manipulators\Contrast;
use Weserv\Images\Manipulators\Crop;
use Weserv\Images\Manipulators\Filter;
use Weserv\Images\Manipulators\Gamma;
use Weserv\Images\Manipulators\Letterbox;
use Weserv\Images\Manipulators\Orientation;
use Weserv\Images\Manipulators\Shape;
use Weserv\Images\Manipulators\Sharpen;
use Weserv\Images\Manipulators\Thumbnail;
use Weserv\Images\Manipulators\Trim;
use Jcupitt\Vips\Image;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

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
     * @param int          $threshold
     */
    public function assertSimilarImage($expectedImage, $actualImage, int $threshold = 5)
    {
        $constraint = new SimilarImageConstraint($expectedImage, $threshold);
        self::assertThat($actualImage, $constraint);
    }

    /**
     * Verify the maximum color distance
     *
     * @param Image|string $expectedImage
     * @param Image|string $actualImage
     * @param float        $threshold
     */
    public function assertMaxColorDistance($expectedImage, $actualImage, float $threshold = 1.0)
    {
        $constraint = new MaxColorDistanceConstraint($expectedImage, $threshold);
        self::assertThat($actualImage, $constraint);
    }

    public function getManipulators()
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

    protected function getMockery($class)
    {
        return \Mockery::mock($class);
    }
}
