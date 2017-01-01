<?php

namespace AndriesLouw\imagesweserv\Throttler;

use Predis\ClientInterface;

class RedisThrottler implements ThrottlerInterface
{
    /**
     * Redis instance.
     *
     * @var ClientInterface
     */
    protected $redis;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Config
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new Redis throttler instance.
     *
     * @param ClientInterface $redis
     * @param array $config
     */
    public function __construct(ClientInterface $redis, array $config)
    {
        $this->redis = $redis;
        $this->config = array_merge([
            'allowed_requests' => 700, // 700 allowed requests
            'minutes' => 3, // In 3 minutes
            'ban_time' => 60, // If exceed, ban for 60 minutes
            'prefix' => '', // Cache key prefix
        ], $config);
        $this->setPrefix($this->config['prefix']);
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set the cache key prefix.
     *
     * @param  string $prefix
     * @return void
     */
    public function setPrefix($prefix)
    {
        $this->prefix = !empty($prefix) ? $prefix . '_' : '';
    }

    /**
     * @inheritDoc
     */
    public function isExceeded($ip, \Closure $hasExceeded): bool
    {
        if ($this->redis->exists($this->prefix . $ip . ':lockout')) {
            return true;
        }
        if ($this->increment($ip, $this->config['minutes']) > $this->config['allowed_requests']) {
            $ttl = $this->config['ban_time'] * 60;
            $banTime = time() + $ttl;
            $hasExceeded($ip, $banTime);
            $this->redis->set($this->prefix . $ip . ':lockout', $banTime, 'ex', $ttl);
            $this->resetAttempts($ip);
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function increment($ip, $decayMinutes = 1): int
    {
        $value = $this->redis->incr($this->prefix . $ip);
        // Check if this increment is new, and if so set expires time
        if ((int)$value === 1) {
            $this->redis->expireat($this->prefix . $ip, time() + ($decayMinutes * 60));
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function attempts($ip): int
    {
        return (int)$this->redis->get($this->prefix . $ip);
    }

    /**
     * @inheritDoc
     */
    public function resetAttempts($ip): bool
    {
        return (bool)$this->redis->del([$this->prefix . $ip]);
    }

    /**
     * @inheritDoc
     */
    public function retriesLeft($ip, $maxAttempts): int
    {
        $attempts = $this->attempts($ip);
        return $attempts === 0 ? $maxAttempts : $maxAttempts - $attempts + 1;
    }

    /**
     * @inheritDoc
     */
    public function clear($ip)
    {
        $this->resetAttempts($ip);
        $this->redis->del([$this->prefix . $ip . ':lockout']);
    }

    /**
     * @inheritDoc
     */
    public function availableIn($ip): int
    {
        return (int)$this->redis->get($this->prefix . $ip . ':lockout') - time();
    }
}