<?php

namespace AndriesLouw\imagesweserv\Test\Throttler;

use AndriesLouw\imagesweserv\Test\ImagesweservTestCase;
use AndriesLouw\imagesweserv\Throttler\RedisThrottler;
use AndriesLouw\imagesweserv\Throttler\ThrottlingPolicy;
use Mockery\MockInterface;
use Predis\ClientInterface;

class RedisThrottlerTest extends ImagesweservTestCase
{
    /**
     * Redis instance.
     *
     * @var ClientInterface|MockInterface
     */
    protected $redis;

    /**
     * Throttling policy.
     *
     * @var ThrottlingPolicy|MockInterface
     */
    protected $policy;

    /**
     * Throttling config.
     *
     * @var array
     */
    protected $config;

    /**
     * The Redis throttler instance.
     *
     * @var RedisThrottler
     */
    protected $throttler;

    public function setUp()
    {
        $this->redis = $this->getMockery(ClientInterface::class);
        $this->policy = $this->getMockery(ThrottlingPolicy::class);
        $this->config = [
            'allowed_requests' => 700, // 700 allowed requests
            'minutes' => 3, // In 3 minutes
            'prefix' => 'c' // Cache key prefix
        ];
        $this->throttler = new RedisThrottler($this->redis, $this->policy, $this->config);
    }

    public function testSetGetPrefix()
    {
        $this->throttler->setPrefix('');
        $this->assertEquals('', $this->throttler->getPrefix());

        $this->throttler->setPrefix($this->config['prefix']);
        $this->assertEquals($this->config['prefix'] . '_', $this->throttler->getPrefix());
    }

    public function testIsLockout()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('exists')->with($prefix . $ipAddress . ':lockout')->andReturn(true);

        $this->assertTrue($this->throttler->isExceeded($ipAddress));
    }

    public function testIsExceeded()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();
        $banTime = 60; // in minutes

        $this->redis->shouldReceive('exists')->with($prefix . $ipAddress . ':lockout')->andReturn(false);
        $this->redis->shouldReceive('incr')->with($prefix . $ipAddress)
            ->andReturn($this->config['allowed_requests'] + 1);

        $this->policy->shouldReceive('getBanTime')->andReturn($banTime);
        $this->policy->shouldReceive('isCloudFlareEnabled')->andReturn(true);
        $this->policy->shouldReceive('banAtCloudFlare')->with($ipAddress)->andReturn(true);

        $this->redis->shouldReceive('set')
            ->with($prefix . $ipAddress . ':lockout', \Mockery::any(), 'ex', $banTime * 60);
        $this->redis->shouldReceive('del')->with([$prefix . $ipAddress])->andReturn(1);

        $this->assertTrue($this->throttler->isExceeded($ipAddress));
    }


    public function testIsNotExceeded()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('exists')->with($prefix . $ipAddress . ':lockout')->andReturn(false);
        $this->redis->shouldReceive('incr')->with($prefix . $ipAddress)->andReturn(1);
        $this->redis->shouldReceive('expireat')->with($prefix . $ipAddress, \Mockery::any());

        $this->assertFalse($this->throttler->isExceeded($ipAddress));
    }

    public function testIncrement()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('incr')->with($prefix . $ipAddress)->andReturn(1);
        $this->redis->shouldReceive('expireat')->with($prefix . $ipAddress, \Mockery::any());


        $this->assertEquals(1, $this->throttler->increment($ipAddress, $this->config['minutes']));
    }

    public function testAttempts()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('get')->with($prefix . $ipAddress)->andReturn('1');

        $this->assertEquals(1, $this->throttler->attempts($ipAddress));
    }

    public function testResetAttempts()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('del')->with([$prefix . $ipAddress])->andReturn(1);

        $this->assertEquals(true, $this->throttler->resetAttempts($ipAddress));
    }

    public function testZeroAttemptsAllRetriesLeft()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('get')->with($prefix . $ipAddress)->andReturn('0');

        $this->assertEquals(
            $this->config['allowed_requests'],
            $this->throttler->retriesLeft($ipAddress, $this->config['allowed_requests'])
        );
    }

    public function testRetriesLeft()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('get')->with($prefix . $ipAddress)->andReturn('2');

        $this->assertEquals(
            $this->config['allowed_requests'] - 1,
            $this->throttler->retriesLeft($ipAddress, $this->config['allowed_requests'])
        );
    }

    public function testClear()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();

        $this->redis->shouldReceive('del')->with([$prefix . $ipAddress])->andReturn(1);
        $this->redis->shouldReceive('del')->with([$prefix . $ipAddress . ':lockout'])->andReturn(1);

        $this->throttler->clear($ipAddress);
    }

    public function testAvailableIn()
    {
        $ipAddress = '127.0.0.1';
        $prefix = $this->throttler->getPrefix();
        $ttl = 3600; // in minutes

        $this->redis->shouldReceive('get')->with($prefix . $ipAddress . ':lockout')->andReturn(time() + $ttl);

        $this->assertEquals($ttl, $this->throttler->availableIn($ipAddress));
    }
}
