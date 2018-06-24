<?php

namespace Weserv\Images\Test\Exception;

use Weserv\Images\Api\Api;
use Weserv\Images\Client;
use Weserv\Images\Exception\RateExceededException;
use Weserv\Images\Server;
use Weserv\Images\Test\ImagesWeservTestCase;
use Weserv\Images\Throttler\ThrottlerInterface;

class RateExceededExceptionTest extends ImagesWeservTestCase
{
    /**
     * Test can construct and throw an exception.
     *
     * @expectedException \Weserv\Images\Exception\RateExceededException
     */
    public function testThrowException()
    {
        throw new RateExceededException();
    }

    /**
     * @expectedException        \Weserv\Images\Exception\RateExceededException
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
