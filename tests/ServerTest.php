<?php

namespace AndriesLouw\imagesweserv\Test;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Api\ApiInterface;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Manipulators\ManipulatorInterface;
use AndriesLouw\imagesweserv\Server;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;
use League\Uri\Schemes\Http as HttpUri;

class ServerTest extends ImagesweservTestCase
{
    private $server;

    public function setUp()
    {
        $this->server = new Server($this->getMockery(ApiInterface::class));
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Server::class, $this->server);
    }

    public function testSetApi()
    {
        $api = $this->getMockery(ApiInterface::class);
        $this->server->setApi($api);
        $this->assertInstanceOf(ApiInterface::class, $this->server->getApi());
    }

    public function testGetApi()
    {
        $this->assertInstanceOf(ApiInterface::class, $this->server->getApi());
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

        $client = $this->getMockery(Client::class, function ($mock) use ($uri, $tempFile) {
            $mock->shouldReceive('get')->with($uri->__toString())->andReturn($tempFile);
        });

        $throttler = $this->getMockery(ThrottlerInterface::class, function ($mock) {
            $mock->shouldReceive('isExceeded')->with('127.0.0.1');
        });

        $image = $this->getMockery(Image::class, function ($mock) use ($pixel) {
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

        $manipulator = $this->getMockery(ManipulatorInterface::class,
            function ($mock) use ($image, $params) {
                $mock->shouldReceive('setParams')
                    ->with($params)
                    ->andReturnSelf();

                $mock->shouldReceive('getParams')
                    ->andReturn($params);

                $mock->shouldReceive('run')->andReturn($image);
            });

        $api = new Api($client, $throttler, [$manipulator]);

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

        $client = $this->getMockery(
            Client::class,
            function ($mock) use ($uri, $tempFile) {
                $mock->shouldReceive('get')->with($uri->__toString())->andReturn($tempFile);
            }
        );

        $throttler = $this->getMockery(
            ThrottlerInterface::class,
            function ($mock) {
                $mock->shouldReceive('isExceeded')->with('127.0.0.1');
            }
        );

        $image = $this->getMockery(Image::class, function ($mock) use ($pixel) {
            $mock->shouldReceive('writeToBuffer')->andReturn($pixel);
        });

        $params = [
            'accessMethod' => Access::SEQUENTIAL,
            'tmpFileName' => $tempFile,
            'loader' => 'VipsForeignLoadPng',
            'hasAlpha' => true,
            'is16Bit' => false,
            'isPremultiplied' => false,
            'encoding' => 'base64'
        ];

        $manipulator = $this->getMockery(ManipulatorInterface::class,
            function ($mock) use ($image, $params) {
                $mock->shouldReceive('setParams')
                    ->with($params)
                    ->andReturnSelf();

                $mock->shouldReceive('getParams')
                    ->andReturn($params);

                $mock->shouldReceive('run')->andReturn($image);
            });

        $api = new Api($client, $throttler, [$manipulator]);

        $this->server->setApi($api);
        ob_start();
        $response = $this->server->outputImage($uri, ['encoding' => 'base64']);
        $content = ob_get_clean();
        $this->assertNull($response);
        $this->assertEquals(sprintf('data:%s;base64,%s', $type, $base64), $content);

        unlink($tempFile);
    }
}
