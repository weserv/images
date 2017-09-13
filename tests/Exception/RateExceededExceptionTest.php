<?php

namespace AndriesLouw\imagesweserv\Test\Exception;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Exception\RateExceededException;
use AndriesLouw\imagesweserv\Server;
use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;

class RateExceededExceptionTest extends ImagesweservTestCase
{
    /**
     * Test can construct the exception, then throw it.
     *
     * @expectedException \AndriesLouw\imagesweserv\Exception\RateExceededException
     */
    public function testThrowException()
    {
        $exception = new RateExceededException();
        throw $exception;
    }

    /**
     * @expectedException        \AndriesLouw\imagesweserv\Exception\RateExceededException
     * @expectedExceptionMessage There are an unusual number of requests coming from this IP address.
     */
    public function testRateExceededException()
    {
        $client = $this->getMockery(Client::class);
        $api = new Api($client, $this->getManipulators());
        $throttler = $this->getMockery(ThrottlerInterface::class);
        $throttler->shouldReceive('isExceeded')->with('127.0.0.1')->andReturn(true);

        $server = new Server($api, $throttler);
        $server->outputImage('foobar.jpg', []);
    }
}
