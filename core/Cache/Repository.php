<?php

namespace Vayu\Cache;

/**
 * High-level cache API over a Store.
 *
 *   Cache::put('k', 'v', 60);
 *   Cache::remember('users', 300, fn () => User::all());
 */
class Repository
{
    public function __construct(
        protected Store $store,
        protected ?int $defaultTtl = null,
    ) {
    }

    public function store(): Store
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key);

        if ($value !== null) {
            return $value;
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    public function has(string $key): bool
    {
        return $this->store->get($key) !== null;
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store->put($key, $value, $ttl ?? $this->defaultTtl);
    }

    public function add(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->put($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->store->put($key, $value, null);
    }

    public function remember(string $key, ?int $ttl, \Closure $callback): mixed
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, \Closure $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->store->forget($key);
    }

    public function flush(): bool
    {
        return $this->store->flush();
    }

    public function increment(string $key, int $value = 1): int
    {
        return $this->store->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->store->decrement($key, $value);
    }
}
