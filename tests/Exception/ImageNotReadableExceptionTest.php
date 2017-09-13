<?php

namespace AndriesLouw\imagesweserv\Test\Exception;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;

class ImageNotReadableExceptionTest extends ImagesweservTestCase
{
    /**
     * Test can construct the exception, then throw it.
     *
     * @expectedException \AndriesLouw\imagesweserv\Exception\ImageNotReadableException
     */
    public function testThrowException()
    {
        $exception = new ImageNotReadableException();
        throw $exception;
    }

    /**
     * @expectedException        \AndriesLouw\imagesweserv\Exception\ImageNotReadableException
     * @expectedExceptionMessage Image not readable. Is it a valid image?
     */
    public function testImageNotReadableException()
    {
        $client = $this->getMockery(Client::class);
        $api = new Api($client, $this->getManipulators());

        $nonExistent = "I don't exist.jpg";

        $client->shouldReceive('get')->with($nonExistent)->andReturn($nonExistent);

        $api->run($nonExistent, []);
    }
}
