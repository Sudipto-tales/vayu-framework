<?php

namespace Vayu\Config;

/**
 * Lazy, dot-notation config repository.
 *
 * Config files in /config that RETURN an array are loaded on demand, so legacy
 * side-effect files (db.php, bootstarp.php, ...) are never executed here.
 */
class Config
{
    protected string $path;

    /** @var array<string, mixed> */
    protected array $loaded = [];

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
    }

    /**
     * Seed the repository with a pre-compiled config map (file name => array),
     * as produced by `php vayu config:cache`. Keys present here are served from
     * memory; any key not in the map still falls back to loading its file.
     *
     * @param array<string, array<string, mixed>> $items
     */
    public function preload(array $items): void
    {
        $this->loaded = $items + $this->loaded;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        [$file, $rest] = array_pad(explode('.', $key, 2), 2, null);

        if (!array_key_exists($file, $this->loaded)) {
            $filePath = $this->path . '/' . $file . '.php';
            $value = is_file($filePath) ? require $filePath : [];
            $this->loaded[$file] = is_array($value) ? $value : [];
        }

        $data = $this->loaded[$file];

        if ($rest === null) {
            return $data;
        }

        foreach (explode('.', $rest) as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return $default;
            }
        }

        return $data;
    }

    public function set(string $key, mixed $value): void
    {
        [$file, $rest] = array_pad(explode('.', $key, 2), 2, null);

        $this->get($file); // ensure loaded
        if ($rest === null) {
            $this->loaded[$file] = $value;

            return;
        }

        $segments = explode('.', $rest);
        $ref = &$this->loaded[$file];
        foreach ($segments as $segment) {
            if (!is_array($ref)) {
                $ref = [];
            }
            if (!array_key_exists($segment, $ref)) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__vayu_missing__') !== '__vayu_missing__';
    }
}
