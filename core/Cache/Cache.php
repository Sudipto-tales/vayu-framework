<?php

namespace Vayu\Cache;

/**
 * Static facade for the default cache repository.
 *
 * @method static mixed  get(string $key, mixed $default = null)
 * @method static bool   has(string $key)
 * @method static bool   put(string $key, mixed $value, ?int $ttl = null)
 * @method static bool   add(string $key, mixed $value, ?int $ttl = null)
 * @method static bool   forever(string $key, mixed $value)
 * @method static mixed  remember(string $key, ?int $ttl, \Closure $callback)
 * @method static mixed  rememberForever(string $key, \Closure $callback)
 * @method static mixed  pull(string $key, mixed $default = null)
 * @method static bool   forget(string $key)
 * @method static bool   flush()
 * @method static int    increment(string $key, int $value = 1)
 * @method static int    decrement(string $key, int $value = 1)
 */
class Cache
{
    public static function store(?string $name = null): Repository
    {
        return \app('cache')->store($name);
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::store()->{$method}(...$arguments);
    }
}
