<?php

namespace Vayu\Cache;

/**
 * Low-level cache store contract. A null TTL means "forever".
 */
interface Store
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, ?int $seconds): bool;

    public function forget(string $key): bool;

    public function flush(): bool;

    public function increment(string $key, int $value = 1): int;

    public function decrement(string $key, int $value = 1): int;
}
