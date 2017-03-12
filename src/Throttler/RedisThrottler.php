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
        if ($this->redis->exists($this->prefix . $ip . ':lockout')) {
            return true;
        }
        if ($this->increment($ip, $this->config['minutes']) > $this->config['allowed_requests']) {
            $ttl = $this->policy->getBanTime() * 60;
            $expires = time() + $ttl;
            // Is CloudFlare enabled?
            if ($this->policy->isCloudFlareEnabled()) {
                $this->policy->banAtCloudFlare($ip);
            }
            $this->redis->set($this->prefix . $ip . ':lockout', $expires, 'ex', $ttl);
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
        $value = $this->redis->incr($this->prefix . $ip);
        // Check if this increment is new, and if so set expires time
        if ((int)$value === 1) {
            $this->redis->expireat($this->prefix . $ip, time() + ($decayMinutes * 60));
        }
        return $value;
    }

    /**
     * Get the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return mixed
     */
    public function attempts($ip): int
    {
        return (int)$this->redis->get($this->prefix . $ip);
    }

    /**
     * Reset the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return bool true on success or false on failure.
     */
    public function resetAttempts($ip): bool
    {
        return (bool)$this->redis->del([$this->prefix . $ip]);
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
        $this->redis->del([$this->prefix . $ip . ':lockout']);
    }

    /**
     * Get the number of seconds until the "IP" is accessible again.
     *
     * @param  string $ip
     * @return int
     */
    public function availableIn($ip): int
    {
        return (int)$this->redis->get($this->prefix . $ip . ':lockout') - time();
    }
}