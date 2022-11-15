<?php

namespace Weserv\Images\Test\Exception;

use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Exception\ImageNotReadableException;
use Weserv\Images\Test\ImagesWeservTestCase;

class ImageNotReadableExceptionTest extends ImagesWeservTestCase
{
    /**
     * Test can construct and throw an exception.
     */
    public function testThrowException(): void
    {
        $this->expectException(ImageNotReadableException::class);
        throw new ImageNotReadableException();
    }

    public function testImageNotReadableException(): void
    {
        $this->expectException(ImageNotReadableException::class);
        $this->expectExceptionMessage('Image not readable. Is it a valid image?');

        $client = $this->getMockery(Client::class);
        $api = new Api($client, $this->getManipulators());

        $nonExistent = "I don't exist.jpg";

        $client->shouldReceive('get')->with($nonExistent)->andReturn($nonExistent);

        $api->run($nonExistent, []);
    }
}
