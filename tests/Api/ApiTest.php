<?php

namespace AndriesLouw\imagesweserv\Api;

use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;
use Mockery;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private $api;

    public function setUp()
    {
        $this->api = new Api(
            Mockery::mock(Client::class),
            Mockery::mock(ThrottlerInterface::class),
            []
        );
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Api::class, $this->api);
    }

    public function testSetThrottler()
    {
        // Test if we can set a `null` throttler
        $this->api->setThrottler(null);
        $this->assertNull($this->api->getThrottler());

        // Test if we can set a `real` throttler
        $this->api->setThrottler(Mockery::mock(ThrottlerInterface::class));
        $this->assertInstanceOf(ThrottlerInterface::class, $this->api->getThrottler());
    }

    public function testGetThrottler()
    {
        $this->assertInstanceOf(ThrottlerInterface::class, $this->api->getThrottler());
    }

    public function testSetClient()
    {
        $this->api->setClient(Mockery::mock(Client::class));
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testGetClient()
    {
        $this->assertInstanceOf(Client::class, $this->api->getClient());
    }

    public function testSetManipulators()
    {
        $this->api->setManipulators([Mockery::mock(ManipulatorInterface::class)]);
        $manipulators = $this->api->getManipulators();
        $this->assertInstanceOf(ManipulatorInterface::class, $manipulators[0]);
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
        /* 1x1 transparent pixel */
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $pixel = base64_decode($base64);
        $extension = 'png';
        $type = 'image/png';

        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');

        file_put_contents($tempFile, $pixel);

        $client = Mockery::mock(Client::class, function ($mock) use ($tempFile) {
            $mock->shouldReceive('get')->andReturn($tempFile);
        });

        $throttler = Mockery::mock(ThrottlerInterface::class, function ($mock) {
            $mock->shouldReceive('isExceeded')->with('127.0.0.1');
        });

        $image = Mockery::mock(Image::class, function ($mock) use ($pixel) {
            $mock->shouldReceive('writeToBuffer')->andReturn($pixel);
        });

        $params = [
            'accessMethod' => Access::SEQUENTIAL,
            'tmpFileName' => $tempFile,
            'loader' => 'VipsForeignLoadPng',
            'hasAlpha' => true,
            'is16Bit' => false,
            'isPremultiplied' => false
        ];

        $manipulator = Mockery::mock(ManipulatorInterface::class, function ($mock) use ($image, $params) {
            $mock->shouldReceive('setParams')
                ->with($params)
                ->andReturnSelf();

            $mock->shouldReceive('getParams')
                ->andReturn($params);

            $mock->shouldReceive('run')->andReturn($image);
        });

        $api = new Api($client, $throttler, [$manipulator]);
        $this->assertEquals([$pixel, $type, $extension], $api->run($tempFile, []));

        unlink($tempFile);
    }
}
