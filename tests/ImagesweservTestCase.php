<?php

namespace AndriesLouw\imagesweserv\Test;

use AndriesLouw\imagesweserv\Manipulators\Background;
use AndriesLouw\imagesweserv\Manipulators\Blur;
use AndriesLouw\imagesweserv\Manipulators\Brightness;
use AndriesLouw\imagesweserv\Manipulators\Contrast;
use AndriesLouw\imagesweserv\Manipulators\Crop;
use AndriesLouw\imagesweserv\Manipulators\Filter;
use AndriesLouw\imagesweserv\Manipulators\Gamma;
use AndriesLouw\imagesweserv\Manipulators\Letterbox;
use AndriesLouw\imagesweserv\Manipulators\Orientation;
use AndriesLouw\imagesweserv\Manipulators\Shape;
use AndriesLouw\imagesweserv\Manipulators\Sharpen;
use AndriesLouw\imagesweserv\Manipulators\Thumbnail;
use AndriesLouw\imagesweserv\Manipulators\Trim;
use Jcupitt\Vips\Image;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * A base test case with some custom expectations.
 */
class ImagesweservTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;
    use FixturesTrait;

    /**
     * Verify similarity of expected vs actual image
     *
     * @param Image|string $expectedImage
     * @param Image|string $actualImage
     * @param int $threshold
     */
    public function assertSimilarImage($expectedImage, $actualImage, int $threshold = 5)
    {
        $constraint = new SimilarImageConstraint($expectedImage, $threshold);
        self::assertThat($actualImage, $constraint);
    }

    public function getManipulators()
    {
        return [
            new Trim(),
            new Thumbnail(71000000),
            new Crop(),
            new Orientation(),
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