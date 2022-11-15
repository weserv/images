<?php

namespace Weserv\Images\Test\Exception;

use Jcupitt\Vips\FFI;
use Mockery;
use Weserv\Images\Exception\ImageTooLargeException;
use Weserv\Images\Manipulators\Thumbnail;
use Weserv\Images\Test\ImagesWeservTestCase;

class ImageTooLargeExceptionTest extends ImagesWeservTestCase
{
    /**
     * Test can construct and throw an exception.
     */
    public function testThrowException(): void
    {
        $this->expectException(ImageTooLargeException::class);
        throw new ImageTooLargeException();
    }

    public function testImageNotReadableException(): void
    {
        $this->expectException(ImageTooLargeException::class);
        $this->expectExceptionMessage(
            'Image is too large for processing. Width x Height should be less than 70 megapixels.'
        );

        $thumbnail = new Thumbnail(71000000);

        $image = Mockery::mock('Jcupitt\Vips\Image[__get]', [FFI::vips()->vips_image_new_temp_file("%s.jpg")])
            ->makePartial();
        $image->shouldReceive('__get')
            ->with('height')
            ->andReturn(240);
        $image->shouldReceive('__get')
            ->with('width')
            ->andReturn(320);

        $thumbnail->checkImageSize($image, 35500000, 35500000);
    }
}
