<?php

namespace Weserv\Images\Throttler;

use Memcached;

class MemcachedThrottler implements ThrottlerInterface
{
    /**
     * Memcached instance.
     */
    protected Memcached $memcached;

    /**
     * Throttling policy.
     */
    protected ThrottlingPolicy $policy;

    /**
     * A string that should be prepended to keys.
     */
    protected string $prefix;

    /**
     * Config
     */
    protected array $config;

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
            'prefix' => '' // Cache key prefix
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
     *
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = !empty($prefix) ? $prefix . '_' : '';
    }

    /**
     * Determine if any rate limits have been exceeded
     *
     * @param string $ipAddress
     *
     * @return bool
     */
    public function isExceeded(string $ipAddress): bool
    {
        if ($this->memcached->get($this->prefix . $ipAddress . ':lockout') !== false) {
            return true;
        }
        if ($this->increment($ipAddress, $this->config['minutes']) > $this->config['allowed_requests']) {
            $expires = time() + ($this->policy->getBanTime() * 60);
            // Is CloudFlare enabled?
            if ($this->policy->isCloudFlareEnabled()) {
                $this->policy->banAtCloudFlare($ipAddress);
            }
            $this->memcached->add($this->prefix . $ipAddress . ':lockout', $expires, $expires);
            $this->resetAttempts($ipAddress);
            return true;
        }
        return false;
    }

    /**
     * Increment the counter for a given ip address for a given decay time.
     *
     * @param string $ipAddress
     * @param float|int $decayMinutes
     *
     * @return int
     */
    public function increment(string $ipAddress, $decayMinutes = 1): int
    {
        return (int)$this->memcached->increment($this->prefix . $ipAddress, 1, 1, time() + ($decayMinutes * 60));
    }

    /**
     * Get the number of attempts for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return int
     */
    public function attempts(string $ipAddress): int
    {
        return $this->memcached->get($this->prefix . $ipAddress);
    }

    /**
     * Reset the number of attempts for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return bool true on success or false on failure.
     */
    public function resetAttempts(string $ipAddress): bool
    {
        return $this->memcached->delete($this->prefix . $ipAddress);
    }

    /**
     * Get the number of retries left for the given ip address.
     *
     * @param string $ipAddress
     * @param int $maxAttempts
     *
     * @return int
     */
    public function retriesLeft(string $ipAddress, int $maxAttempts): int
    {
        $attempts = $this->attempts($ipAddress);
        return $attempts === 0 ? $maxAttempts : $maxAttempts - $attempts + 1;
    }

    /**
     * Clear the hits and lockout for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return void
     */
    public function clear(string $ipAddress): void
    {
        $this->resetAttempts($ipAddress);
        $this->memcached->delete($this->prefix . $ipAddress . ':lockout');
    }

    /**
     * Get the number of seconds until the ip address is accessible again.
     *
     * @param string $ipAddress
     *
     * @return int
     */
    public function availableIn(string $ipAddress): int
    {
        return $this->memcached->get($this->prefix . $ipAddress . ':lockout') - time();
    }
}
