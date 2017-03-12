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
     * Throttling policy.
     *
     * @var ThrottlingPolicy
     */
    protected $policy;

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
     * @param ThrottlingPolicy $policy
     * @param array $config
     */
    public function __construct(Memcached $memcached, ThrottlingPolicy $policy, array $config)
    {
        $this->memcached = $memcached;
        $this->policy = $policy;
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
     * Determine if any rate limits have been exceeded
     *
     * @param string $ip
     *
     * @return bool
     */
    public function isExceeded($ip): bool
    {
        if (($cached = $this->memcached->get($this->prefix . $ip . ':lockout')) !== false) {
            return true;
        }
        if ($this->increment($ip, $this->config['minutes']) > $this->config['allowed_requests']) {
            $expires = time() + ($this->policy->getBanTime() * 60);
            // Is CloudFlare enabled?
            if ($this->policy->isCloudFlareEnabled()) {
                $this->policy->banAtCloudFlare($ip);
            }
            $this->memcached->add($this->prefix . $ip . ':lockout', $expires, $expires);
            $this->resetAttempts($ip);
            return true;
        }
        return false;
    }

    /**
     * Increment the counter for a given IP for a given decay time.
     *
     * @param  string $ip
     * @param  float|int $decayMinutes
     * @return int
     */
    public function increment($ip, $decayMinutes = 1): int
    {
        return (int)$this->memcached->increment($this->prefix . $ip, 1, 1, time() + ($decayMinutes * 60));
    }

    /**
     * Get the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return mixed
     */
    public function attempts($ip): int
    {
        return $this->memcached->get($this->prefix . $ip);
    }

    /**
     * Reset the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return bool true on success or false on failure.
     */
    public function resetAttempts($ip): bool
    {
        return $this->memcached->delete($this->prefix . $ip);
    }

    /**
     * Get the number of retries left for the given IP.
     *
     * @param  string $ip
     * @param  int $maxAttempts
     * @return int
     */
    public function retriesLeft($ip, $maxAttempts): int
    {
        $attempts = $this->attempts($ip);
        return $attempts === 0 ? $maxAttempts : $maxAttempts - $attempts + 1;
    }

    /**
     * Clear the hits and lockout for the given IP.
     *
     * @param  string $ip
     * @return void
     */
    public function clear($ip)
    {
        $this->resetAttempts($ip);
        $this->memcached->delete($this->prefix . $ip . ':lockout');
    }

    /**
     * Get the number of seconds until the "IP" is accessible again.
     *
     * @param  string $ip
     * @return int
     */
    public function availableIn($ip): int
    {
        return $this->memcached->get($this->prefix . $ip . ':lockout') - time();
    }
}