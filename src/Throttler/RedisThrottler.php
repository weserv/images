<?php

namespace Weserv\Images\Throttler;

use Predis\ClientInterface;

class RedisThrottler implements ThrottlerInterface
{
    /**
     * Redis instance.
     */
    protected ClientInterface $redis;

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
     * Create a new Redis throttler instance.
     *
     * @param ClientInterface $redis
     * @param ThrottlingPolicy $policy
     * @param array $config
     */
    public function __construct(ClientInterface $redis, ThrottlingPolicy $policy, array $config)
    {
        $this->redis = $redis;
        $this->policy = $policy;
        $this->config = array_merge([
            'allowed_requests' => 700, // 700 allowed requests
            'minutes' => 3, // In 3 minutes
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
     * @param string $prefix
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
        if ($this->redis->exists($this->prefix . $ipAddress . ':lockout')) {
            return true;
        }
        if ($this->increment($ipAddress, $this->config['minutes']) > $this->config['allowed_requests']) {
            $ttl = $this->policy->getBanTime() * 60;
            $expires = time() + $ttl;
            // Is CloudFlare enabled?
            if ($this->policy->isCloudFlareEnabled()) {
                $this->policy->banAtCloudFlare($ipAddress);
            }
            $this->redis->set($this->prefix . $ipAddress . ':lockout', $expires, 'ex', $ttl);
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
        $value = $this->redis->incr($this->prefix . $ipAddress);
        // Check if this increment is new, and if so set expires time
        if ($value === 1) {
            $this->redis->expireat($this->prefix . $ipAddress, time() + ($decayMinutes * 60));
        }
        return $value;
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
        return (int)$this->redis->get($this->prefix . $ipAddress);
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
        return (bool)$this->redis->del([$this->prefix . $ipAddress]);
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
        $this->redis->del([$this->prefix . $ipAddress . ':lockout']);
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
        return (int)$this->redis->get($this->prefix . $ipAddress . ':lockout') - time();
    }
}
