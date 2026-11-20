<?php

namespace Vayu\Session;

/**
 * Session wrapper with flash data, old input, an errors bag and a CSRF token.
 *
 * Native mode binds to $_SESSION (PHP persists it on shutdown). Array mode
 * (native = false) keeps everything in memory — handy for tests.
 */
class Session
{
    /** @var array<string, mixed> */
    protected array $attributes = [];

    protected bool $started = false;

    public function __construct(protected bool $native = true)
    {
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if ($this->native && PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($this->native && isset($_SESSION)) {
            $this->attributes = &$_SESSION;
        }

        $this->started = true;
        $this->ageFlashData();
        $this->ensureToken();
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /* ---- Basic store ---- */

    public function all(): array
    {
        return $this->attributes;
    }

    public function has(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function flush(): void
    {
        $this->attributes = [];
    }

    public function regenerate(): void
    {
        if ($this->native && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $this->regenerateToken();
    }

    /* ---- Flash data (lives for the next request) ---- */

    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $new = $this->get('__flash_new', []);
        $new[] = $key;
        $this->put('__flash_new', array_values(array_unique($new)));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function flashInput(array $input): void
    {
        $this->flash('__old_input', $input);
    }

    public function old(string $key, mixed $default = null): mixed
    {
        $old = $this->get('__old_input', []);

        return $old[$key] ?? $default;
    }

    public function hasOldInput(): bool
    {
        return $this->get('__old_input', []) !== [];
    }

    protected function ageFlashData(): void
    {
        foreach ($this->get('__flash_old', []) as $key) {
            $this->forget($key);
        }

        $this->put('__flash_old', $this->get('__flash_new', []));
        $this->put('__flash_new', []);
    }

    /* ---- CSRF token ---- */

    public function token(): string
    {
        $this->ensureToken();

        return (string) $this->get('__token');
    }

    public function regenerateToken(): void
    {
        $this->put('__token', bin2hex(random_bytes(20)));
    }

    protected function ensureToken(): void
    {
        if (!$this->has('__token')) {
            $this->regenerateToken();
        }
    }
}
