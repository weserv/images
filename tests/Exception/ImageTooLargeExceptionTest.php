<?php

namespace AndriesLouw\imagesweserv\Test\Exception;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Thumbnail;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use Mockery;

class ImageTooLargeExceptionTest extends ImagesweservTestCase
{
    /**
     * Test can construct and throw an exception.
     *
     * @expectedException \AndriesLouw\imagesweserv\Exception\ImageTooLargeException
     */
    public function testThrowException()
    {
        throw new ImageTooLargeException();
    }

    /**
     * @expectedException        \AndriesLouw\imagesweserv\Exception\ImageTooLargeException
     * @expectedExceptionMessage Image is too large for processing. Width x Height should be less than 70 megapixels.
     */
    public function testImageNotReadableException()
    {
        $thumbnail = new Thumbnail(71000000);

        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', ['']);
        $image->shouldReceive('__get')
            ->with('height')
            ->andReturn(240);
        $image->shouldReceive('__get')
            ->with('width')
            ->andReturn(320);

        $thumbnail->checkImageSize($image, 35500000, 35500000);
    }
}
