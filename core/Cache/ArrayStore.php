<?php

namespace Vayu\Cache;

/**
 * In-memory cache store (per-process). Used by the `array` driver and tests.
 */
class ArrayStore implements Store
{
    /** @var array<string, array{expires: int, value: mixed}> */
    protected array $storage = [];

    public function __construct(protected string $prefix = '')
    {
    }

    public function get(string $key): mixed
    {
        $key = $this->prefix . $key;
        if (!isset($this->storage[$key])) {
            return null;
        }

        $entry = $this->storage[$key];
        if ($entry['expires'] !== 0 && $entry['expires'] < time()) {
            unset($this->storage[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function put(string $key, mixed $value, ?int $seconds): bool
    {
        $this->storage[$this->prefix . $key] = [
            'expires' => $seconds === null ? 0 : time() + $seconds,
            'value' => $value,
        ];

        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$this->prefix . $key]);

        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];

        return true;
    }

    public function increment(string $key, int $value = 1): int
    {
        $current = (int) ($this->get($key) ?? 0);
        $new = $current + $value;

        $existing = $this->storage[$this->prefix . $key]['expires'] ?? 0;
        $this->storage[$this->prefix . $key] = ['expires' => $existing, 'value' => $new];

        return $new;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }
}
