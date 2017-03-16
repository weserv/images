<?php

namespace AndriesLouw\imagesweserv;

use Jcupitt\Vips\Access;
use League\Uri\Schemes\Http as HttpUri;
use Mockery;
use PHPUnit\Framework\TestCase;

class ServerTest extends TestCase
{
    private $server;

    public function setUp()
    {
        $this->server = new Server(Mockery::mock('AndriesLouw\imagesweserv\Api\ApiInterface'));
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Server', $this->server);
    }

    public function testSetApi()
    {
        $api = Mockery::mock('AndriesLouw\imagesweserv\Api\ApiInterface');
        $this->server->setApi($api);
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Api\ApiInterface', $this->server->getApi());
    }

    public function testGetApi()
    {
        $this->assertInstanceOf('AndriesLouw\imagesweserv\Api\ApiInterface', $this->server->getApi());
    }

    public function testSetDefaults()
    {
        $defaults = [
            'output' => 'png'
        ];

        $this->server->setDefaults($defaults);

        $this->assertSame($defaults, $this->server->getDefaults());
    }

    public function testSetPresets()
    {
        $presets = [
            'small' => [
                'w' => '200',
                'h' => '200',
                'fit' => 'crop',
            ],
        ];

        $this->server->setPresets($presets);

        $this->assertSame($presets, $this->server->getPresets());
    }

    public function testGetAllParams()
    {
        $this->server->setDefaults([
            'output' => 'png'
        ]);

        $this->server->setPresets([
            'small' => [
                'w' => '200',
                'h' => '200',
                'fit' => 'crop',
            ],
        ]);

        $all_params = $this->server->getAllParams([
            'w' => '100',
            'p' => 'small',
        ]);

        $this->assertSame([
            'output' => 'png',
            'w' => '100',
            'h' => '200',
            'fit' => 'crop',
            'p' => 'small',
        ], $all_params);
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputImage()
    {
        /* 1x1 transparent pixel */
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $pixel = base64_decode($base64);
        $extension = 'png';

        $uri = HttpUri::createFromString('https://images.weserv.nl/pixel.' . $extension);

        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');

        file_put_contents($tempFile, $pixel);

        $client = Mockery::mock(
            'AndriesLouw\imagesweserv\Client',
            function ($mock) use ($uri, $tempFile) {
                $mock->shouldReceive('get')->with($uri->__toString())->andReturn($tempFile);
            }
        );

        $throttler = Mockery::mock(
            'AndriesLouw\imagesweserv\Throttler\ThrottlerInterface',
            function ($mock) {
                $mock->shouldReceive('isExceeded')->with('127.0.0.1');
            }
        );

        $image = Mockery::mock('Jcupitt\Vips\Image', function ($mock) use ($pixel) {
            $mock->shouldReceive('writeToBuffer')->andReturn($pixel);
        });

        $manipulator = Mockery::mock(
            'AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface',
            function ($mock) use ($image, $tempFile) {
                $mock->shouldReceive('setParams')->with([
                    'accessMethod' => Access::SEQUENTIAL,
                    'tmpFileName' => $tempFile,
                    'loader' => 'VipsForeignLoadPng',
                    'hasAlpha' => true,
                    'is16Bit' => false,
                    'isPremultiplied' => false,
                    'rotation' => 0,
                    'flip' => false,
                    'flop' => false,
                    'cropCoordinates' => null
                ]);
                $mock->shouldReceive('run')->andReturn($image);
            }
        );

        $api = new Api\Api($client, $throttler, [$manipulator]);

        $this->server->setApi($api);
        ob_start();
        $response = $this->server->outputImage($uri, []);
        $content = ob_get_clean();
        $this->assertNull($response);
        $this->assertEquals($pixel, $content);

        unlink($tempFile);
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputImageAsBase64()
    {
        /* 1x1 transparent pixel */
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $pixel = base64_decode($base64);
        $extension = 'png';
        $type = 'image/png';

        $uri = HttpUri::createFromString('https://images.weserv.nl/pixel.' . $extension);

        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');

        file_put_contents($tempFile, $pixel);

        $client = Mockery::mock(
            'AndriesLouw\imagesweserv\Client',
            function ($mock) use ($uri, $tempFile) {
                $mock->shouldReceive('get')->with($uri->__toString())->andReturn($tempFile);
            }
        );

        $throttler = Mockery::mock(
            'AndriesLouw\imagesweserv\Throttler\ThrottlerInterface',
            function ($mock) {
                $mock->shouldReceive('isExceeded')->with('127.0.0.1');
            }
        );

        $image = Mockery::mock('Jcupitt\Vips\Image', function ($mock) use ($pixel) {
            $mock->shouldReceive('writeToBuffer')->andReturn($pixel);
        });

        $manipulator = Mockery::mock(
            'AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface',
            function ($mock) use ($image, $tempFile) {
                $mock->shouldReceive('setParams')->with([
                    'accessMethod' => Access::SEQUENTIAL,
                    'tmpFileName' => $tempFile,
                    'loader' => 'VipsForeignLoadPng',
                    'hasAlpha' => true,
                    'is16Bit' => false,
                    'isPremultiplied' => false,
                    'encoding' => 'base64',
                    'rotation' => 0,
                    'flip' => false,
                    'flop' => false,
                    'cropCoordinates' => null
                ]);
                $mock->shouldReceive('run')->andReturn($image);
            }
        );

        $api = new Api\Api($client, $throttler, [$manipulator]);

        $this->server->setApi($api);
        ob_start();
        $response = $this->server->outputImage($uri, ['encoding' => 'base64']);
        $content = ob_get_clean();
        $this->assertNull($response);
        $this->assertEquals(sprintf('data:%s;base64,%s', $type, $base64), $content);

        unlink($tempFile);
    }
}
