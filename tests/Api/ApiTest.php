<?php

namespace AndriesLouw\imagesweserv\Api;

use Mockery;

// TODO API output
class ApiTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->api = new Api(Mockery::mock('AndriesLouw\imagesweserv\Client'), []);
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Api\Api', $this->api);
    }

    public function testSetClient()
    {
        $this->api->setClient(Mockery::mock('AndriesLouw\imagesweserv\Client'));
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Client', $this->api->getClient());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Client', $this->api->getClient());
    }

    public function testSetManipulators()
    {
        $this->api->setManipulators([Mockery::mock('AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface')]);
        $manipulators = $this->api->getManipulators();
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface', $manipulators[0]);
    }

    public function testSetInvalidManipulator()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->api->setManipulators([new \StdClass()]);
    }

    public function testGetManipulators()
    {
        $this->assertEquals([], $this->api->getManipulators());
    }

    public function testRun()
    {

    }
}
