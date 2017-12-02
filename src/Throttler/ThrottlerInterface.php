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
     * @param string $ipAddress
     *
     * @return bool
     */
    public function isExceeded(string $ipAddress): bool;

    /**
     * Increment the counter for a given ip address for a given decay time.
     *
     * @param string $ipAddress
     * @param float|int $decayMinutes
     *
     * @return int
     */
    public function increment(string $ipAddress, $decayMinutes = 1): int;

    /**
     * Get the number of attempts for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return int
     */
    public function attempts(string $ipAddress): int;

    /**
     * Reset the number of attempts for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return bool true on success or false on failure.
     */
    public function resetAttempts(string $ipAddress): bool;

    /**
     * Get the number of retries left for the given ip address.
     *
     * @param string $ipAddress
     * @param int $maxAttempts
     *
     * @return int
     */
    public function retriesLeft(string $ipAddress, int $maxAttempts): int;

    /**
     * Clear the hits and lockout for the given ip address.
     *
     * @param string $ipAddress
     *
     * @return void
     */
    public function clear(string $ipAddress);

    /**
     * Get the number of seconds until the ip address is accessible again.
     *
     * @param string $ipAddress
     *
     * @return int
     */
    public function availableIn(string $ipAddress): int;
}
