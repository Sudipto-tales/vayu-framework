<?php

namespace Vayu\Cache;

/**
 * Filesystem cache store. Each entry is a serialized ['expires' => int,
 * 'value' => mixed] payload (expires 0 = forever).
 */
class FileStore implements Store
{
    public function __construct(
        protected string $directory,
        protected string $prefix = '',
    ) {
    }

    public function get(string $key): mixed
    {
        $payload = $this->readPayload($key);

        return $payload === null ? null : $payload['value'];
    }

    public function put(string $key, mixed $value, ?int $seconds): bool
    {
        $expires = $seconds === null ? 0 : time() + $seconds;
        $data = serialize(['expires' => $expires, 'value' => $value]);

        $file = $this->path($key);
        $this->ensureDir();

        return @file_put_contents($file, $data, LOCK_EX) !== false;
    }

    public function forget(string $key): bool
    {
        $file = $this->path($key);

        return !is_file($file) || @unlink($file);
    }

    public function flush(): bool
    {
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }

        return true;
    }

    public function increment(string $key, int $value = 1): int
    {
        $this->ensureDir();
        $file = $this->path($key);
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return $value;
        }

        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle) ?: '';
        $payload = $raw !== '' ? @unserialize($raw) : false;

        $now = time();
        if (!is_array($payload) || ($payload['expires'] !== 0 && $payload['expires'] < $now)) {
            $current = 0;
            $expires = 0;
        } else {
            $current = (int) $payload['value'];
            $expires = (int) $payload['expires'];
        }

        $new = $current + $value;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, serialize(['expires' => $expires, 'value' => $new]));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $new;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    /**
     * @return array{expires: int, value: mixed}|null
     */
    protected function readPayload(string $key): ?array
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }

        $payload = @unserialize((string) @file_get_contents($file));
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }

        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            $this->forget($key);

            return null;
        }

        return $payload;
    }

    protected function path(string $key): string
    {
        return $this->directory . '/' . sha1($this->prefix . $key) . '.cache';
    }

    protected function ensureDir(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }
}
