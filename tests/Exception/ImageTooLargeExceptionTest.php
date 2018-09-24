<?php

namespace Weserv\Images\Test\Exception;

use Mockery;
use Weserv\Images\Exception\ImageTooLargeException;
use Weserv\Images\Manipulators\Thumbnail;
use Weserv\Images\Test\ImagesWeservTestCase;

class ImageTooLargeExceptionTest extends ImagesWeservTestCase
{
    /**
     * Test can construct and throw an exception.
     *
     * @expectedException \Weserv\Images\Exception\ImageTooLargeException
     */
    public function testThrowException(): void
    {
        throw new ImageTooLargeException();
    }

    /**
     * @expectedException        \Weserv\Images\Exception\ImageTooLargeException
     * @expectedExceptionMessage Image is too large for processing. Width x Height should be less than 70 megapixels.
     */
    public function testImageNotReadableException(): void
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
