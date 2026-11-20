<?php

namespace Vayu\Cache;

use Vayu\Application;

/**
 * Resolves cache repositories from config/cache.php.
 */
class CacheManager
{
    /** @var array<string, Repository> */
    protected array $stores = [];

    public function __construct(protected Application $app)
    {
    }

    public function store(?string $name = null): Repository
    {
        $name ??= (string) $this->app->config('cache.default', 'file');

        return $this->stores[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Repository
    {
        $config = $this->app->config("cache.stores.{$name}");
        if (!is_array($config)) {
            throw new \RuntimeException("Cache store [{$name}] is not configured.");
        }

        $prefix = (string) $this->app->config('cache.prefix', 'vayu_');
        $ttl = $this->app->config('cache.ttl');
        $ttl = $ttl === null ? null : (int) $ttl;

        $store = match ($config['driver']) {
            'file' => new FileStore(
                $this->resolvePath((string) ($config['path'] ?? 'cache/data')),
                $prefix
            ),
            'array' => new ArrayStore($prefix),
            'redis' => new RedisStore($config, $prefix),
            default => throw new \RuntimeException("Unsupported cache driver [{$config['driver']}]."),
        };

        return new Repository($store, $ttl);
    }

    protected function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->app->storagePath($path);
    }
}
