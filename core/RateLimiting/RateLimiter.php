<?php

namespace Vayu\RateLimiting;

use Vayu\Cache\Repository;

/**
 * Fixed-window rate limiter backed by the cache store.
 */
class RateLimiter
{
    public function __construct(protected Repository $cache)
    {
    }

    /**
     * Increment the counter for a key and return the new attempt count.
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $timerKey = $key . ':timer';

        if (!$this->cache->has($timerKey)) {
            $this->cache->put($timerKey, time() + $decaySeconds, $decaySeconds);
            $this->cache->put($key, 0, $decaySeconds);
        }

        return $this->cache->increment($key);
    }

    public function attempts(string $key): int
    {
        return (int) ($this->cache->get($key) ?? 0);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Seconds until the current window resets.
     */
    public function availableIn(string $key): int
    {
        return max(0, (int) ($this->cache->get($key . ':timer') ?? 0) - time());
    }

    public function clear(string $key): void
    {
        $this->cache->forget($key);
        $this->cache->forget($key . ':timer');
    }
}
