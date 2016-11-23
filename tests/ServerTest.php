<?php

namespace AndriesLouw\imagesweserv;

use Mockery;

// TODO Server output
class ServerTest extends \PHPUnit_Framework_TestCase
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

    public function testGetDefaults()
    {
        $this->testSetDefaults();
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

    public function testGetPresets()
    {
        $this->testSetPresets();
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

    public function testGetImageResponse()
    {

    }


    public function testGetImageAsBase64()
    {

    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputImage()
    {

    }
}
