<?php

namespace Vayu\Cache;

/**
 * Redis cache store (requires the phpredis extension).
 *
 * Values are serialized so any PHP type round-trips.
 */
class RedisStore implements Store
{
    protected ?\Redis $client = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected array $config = [],
        protected string $prefix = '',
    ) {
    }

    public function get(string $key): mixed
    {
        $value = $this->client()->get($this->prefix . $key);

        return $value === false ? null : unserialize($value);
    }

    public function put(string $key, mixed $value, ?int $seconds): bool
    {
        $serialized = serialize($value);
        $key = $this->prefix . $key;

        if ($seconds === null) {
            return (bool) $this->client()->set($key, $serialized);
        }

        return (bool) $this->client()->setex($key, max(1, $seconds), $serialized);
    }

    public function forget(string $key): bool
    {
        return $this->client()->del($this->prefix . $key) >= 0;
    }

    public function flush(): bool
    {
        return (bool) $this->client()->flushDB();
    }

    public function increment(string $key, int $value = 1): int
    {
        return (int) $this->client()->incrBy($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return (int) $this->client()->decrBy($this->prefix . $key, $value);
    }

    protected function client(): \Redis
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!class_exists(\Redis::class)) {
            throw new \RuntimeException('The redis cache driver requires the phpredis extension.');
        }

        $client = new \Redis();
        $client->connect(
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 6379),
            (float) ($this->config['timeout'] ?? 2.0)
        );

        if (!empty($this->config['password'])) {
            $client->auth((string) $this->config['password']);
        }
        if (isset($this->config['database'])) {
            $client->select((int) $this->config['database']);
        }

        return $this->client = $client;
    }
}
