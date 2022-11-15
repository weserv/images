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
     */
    public function testThrowException(): void
    {
        $this->expectException(RateExceededException::class);
        throw new RateExceededException();
    }

    public function testRateExceededException(): void
    {
        $this->expectException(RateExceededException::class);
        $this->expectExceptionMessage('There are an unusual number of requests coming from this IP address.');

        $client = $this->getMockery(Client::class);
        $api = new Api($client, $this->getManipulators());
        $throttler = $this->getMockery(ThrottlerInterface::class);
        $throttler->shouldReceive('isExceeded')->with('127.0.0.1')->andReturn(true);

        $server = new Server($api, $throttler);
        $server->outputImage('foobar.jpg', []);
    }
}
