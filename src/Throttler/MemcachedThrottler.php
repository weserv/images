<?php

namespace AndriesLouw\imagesweserv\Throttler;

use Memcached;

class MemcachedThrottler implements ThrottlerInterface
{
    /**
     * Memcached instance.
     *
     * @var Memcached
     */
    protected $memcached;

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
     * Create a new Memcached throttler instance.
     *
     * @param Memcached $memcached
     * @param array $config
     */
    public function __construct(Memcached $memcached, array $config)
    {
        $this->memcached = $memcached;
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
        if (($cached = $this->memcached->get($this->prefix . $ip . ':lockout')) !== false) {
            return true;
        }
        if ($this->increment($ip, $this->config['minutes']) > $this->config['allowed_requests']) {
            $banTime = time() + ($this->config['ban_time'] * 60);
            $hasExceeded($ip, $banTime);
            $this->memcached->add($this->prefix . $ip . ':lockout', $banTime, $banTime);
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
        return (int)$this->memcached->increment($this->prefix . $ip, 1, 1, time() + ($decayMinutes * 60));
    }

    /**
     * @inheritDoc
     */
    public function attempts($ip): int
    {
        return $this->memcached->get($this->prefix . $ip);
    }

    /**
     * @inheritDoc
     */
    public function resetAttempts($ip): bool
    {
        return $this->memcached->delete($this->prefix . $ip);
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
        $this->memcached->delete($this->prefix . $ip . ':lockout');
    }

    /**
     * @inheritDoc
     */
    public function availableIn($ip): int
    {
        return $this->memcached->get($this->prefix . $ip . ':lockout') - time();
    }
}