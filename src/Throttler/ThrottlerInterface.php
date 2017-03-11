<?php

namespace AndriesLouw\imagesweserv\Throttler;

/**
 * A limiter implements logic to determine if any limits have been exceeded
 */
interface ThrottlerInterface
{
    /**
     * Determine if any rate limits have been exceeded
     *
     * @param string $ip
     *
     * @return bool
     */
    public function isExceeded($ip): bool;

    /**
     * Increment the counter for a given IP for a given decay time.
     *
     * @param  string $ip
     * @param  float|int $decayMinutes
     * @return int
     */
    public function increment($ip, $decayMinutes = 1): int;

    /**
     * Get the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return mixed
     */
    public function attempts($ip): int;

    /**
     * Reset the number of attempts for the given IP.
     *
     * @param  string $ip
     * @return bool true on success or false on failure.
     */
    public function resetAttempts($ip): bool;

    /**
     * Get the number of retries left for the given IP.
     *
     * @param  string $ip
     * @param  int $maxAttempts
     * @return int
     */
    public function retriesLeft($ip, $maxAttempts): int;

    /**
     * Clear the hits and lockout for the given IP.
     *
     * @param  string $ip
     * @return void
     */
    public function clear($ip);

    /**
     * Get the number of seconds until the "IP" is accessible again.
     *
     * @param  string $ip
     * @return int
     */
    public function availableIn($ip): int;
}