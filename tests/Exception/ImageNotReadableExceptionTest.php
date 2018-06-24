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
     *
     * @expectedException \Weserv\Images\Exception\ImageNotReadableException
     */
    public function testThrowException(): void
    {
        throw new ImageNotReadableException();
    }

    /**
     * @expectedException        \Weserv\Images\Exception\ImageNotReadableException
     * @expectedExceptionMessage Image not readable. Is it a valid image?
     */
    public function testImageNotReadableException(): void
    {
        $client = $this->getMockery(Client::class);
        $api = new Api($client, $this->getManipulators());

        $nonExistent = "I don't exist.jpg";

        $client->shouldReceive('get')->with($nonExistent)->andReturn($nonExistent);

        $api->run($nonExistent, []);
    }
}
