<?php

namespace AndriesLouw\imagesweserv\Test;

use AndriesLouw\imagesweserv\Api\Api;
use AndriesLouw\imagesweserv\Api\ApiInterface;
use AndriesLouw\imagesweserv\Client;
use AndriesLouw\imagesweserv\Server;
use AndriesLouw\imagesweserv\Throttler\ThrottlerInterface;
use Jcupitt\Vips\Image;
use Mockery\MockInterface;

class ServerTest extends ImagesweservTestCase
{
    /**
     * @var Client|MockInterface $client
     */
    private $client;

    /**
     * @var Api $api
     */
    private $api;

    /**
     * @var ThrottlerInterface|MockInterface $throttler
     */
    private $throttler;

    /**
     * @var Server $server
     */
    private $server;

    public function setUp()
    {
        $this->client = $this->getMockery(Client::class);
        $this->api = new Api($this->client, $this->getManipulators());
        $this->throttler = $this->getMockery(ThrottlerInterface::class);
        $this->server = new Server($this->api, $this->throttler);
    }

    public function testCreateInstance()
    {
        $this->assertInstanceOf(Server::class, $this->server);
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

        $allParams = $this->server->getAllParams([
            'w' => '100',
            'p' => 'small',
        ]);

        $this->assertSame([
            'output' => 'png',
            'w' => '100',
            'h' => '200',
            'fit' => 'crop',
            'p' => 'small',
        ], $allParams);
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputImage()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'output' => 'jpg'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $content = ob_get_clean();

        $image = Image::newFromBuffer($content);

        $this->assertEquals('jpegload_buffer', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(261, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputImageAsBase64()
    {
        $testImage = $this->inputJpg;
        $params = [
            'encoding' => 'base64'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $content = ob_get_clean();

        $this->assertStringStartsWith('data:image/jpeg;base64', $content);
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputDebugInfo()
    {
        $testImage = $this->inputJpgWithCmykNoProfile;
        $params = [
            'debug' => '1'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $content = ob_get_clean();

        $this->assertContains('debug: newFromFile', $content);
    }

    /**
     * @runInSeparateProcess
     * @requires extension xdebug
     */
    public function testContentDispositionAttachmentHeader()
    {
        $testImage = $this->inputJpg;
        $params = [
            'download' => '1'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $headers = xdebug_get_headers();
        ob_end_clean();

        $this->assertContains('Content-Disposition: attachment; filename=image.jpg', $headers);
    }

    /**
     * @runInSeparateProcess
     * @requires extension gd
     */
    public function testOutputImageAsGif()
    {
        $testImage = $this->inputPngWithGreyAlpha;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'output' => 'gif'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $content = ob_get_clean();

        $image = Image::newFromBuffer($content);

        $this->assertEquals('gifload_buffer', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertTrue($image->hasAlpha());
    }

    /**
     * @runInSeparateProcess
     * @requires extension gd
     */
    public function testOutputImageAsInterlacedGif()
    {
        $testImage = $this->inputJpg;
        $params = [
            'w' => '320',
            'h' => '240',
            't' => 'square',
            'il' => 'true',
            'output' => 'gif'
        ];

        $uri = basename($testImage);

        $this->client->shouldReceive('get')->with($uri)->andReturn($testImage);
        $this->throttler->shouldReceive('isExceeded')->with('127.0.0.1');

        ob_start();
        $this->server->outputImage($uri, $params);
        $content = ob_get_clean();

        $image = Image::newFromBuffer($content);

        $this->assertEquals('gifload_buffer', $image->get('vips-loader'));
        $this->assertEquals(320, $image->width);
        $this->assertEquals(240, $image->height);
        $this->assertFalse($image->hasAlpha());
    }

    public function testGetBufferOptions()
    {
        $this->assertSame([
            'strip' => true,
            'Q' => 85,
            'interlace' => true,
            'optimize_coding' => true
        ], $this->server->getBufferOptions(['il' => '1'], 'jpg'));
        $this->assertSame([
            'interlace' => true,
            'compression' => 6,
            'filter' => 'all'
        ], $this->server->getBufferOptions(['il' => '1', 'filter' => 1], 'png'));
        $this->assertSame([
            'strip' => true,
            'Q' => 85,
            'alpha_q' => 100
        ], $this->server->getBufferOptions([], 'webp'));
        $this->assertSame([
            'strip' => true,
            'Q' => 85,
            'compression' => 'jpeg'
        ], $this->server->getBufferOptions([], 'tiff'));
    }

    public function testGetQuality()
    {
        $this->assertSame(1, $this->server->getQuality(['q' => '1'], 'jpg'));
        $this->assertSame(100, $this->server->getQuality(['q' => '100'], 'jpg'));
        $this->assertSame(85, $this->server->getQuality(['q' => '0'], 'jpg'));
        $this->assertSame(0, $this->server->getQuality(['level' => '0'], 'png'));
        $this->assertSame(9, $this->server->getQuality(['level' => '9'], 'png'));
        $this->assertSame(6, $this->server->getQuality(['level' => '10'], 'png'));
    }

    public function testSetThrottler()
    {
        // Test if we can set a `null` throttler
        $this->server->setThrottler(null);
        $this->assertNull($this->server->getThrottler());

        // Test if we can set a `real` throttler
        $this->server->setThrottler($this->getMockery(ThrottlerInterface::class));
        $this->assertInstanceOf(ThrottlerInterface::class, $this->server->getThrottler());
    }
}
