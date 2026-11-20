<?php

namespace Vayu;

use Vayu\Config\Config;
use Vayu\Console\Kernel as ConsoleKernel;
use Vayu\Exceptions\Handler;
use Vayu\Http\Request;
use Vayu\Logging\Logger;
use Vayu\Routing\Router;

/**
 * The Vayu application: a tiny service registry plus the HTTP/console
 * lifecycle. Intentionally not a full DI container — just enough to stop the
 * old `global $pdo` / loose-function style.
 */
class Application
{
    public const VERSION = '2.0.0-dev';

    protected static ?Application $instance = null;

    protected string $basePath;

    /** @var array<string, callable> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        static::$instance = $this;

        $this->instance(self::class, $this);
        $this->instance('app', $this);

        $this->singleton('config', function (): Config {
            $config = new Config($this->configPath());

            // Fast path: load the compiled config map if `config:cache` ran.
            $cacheFile = $this->storagePath('cache/config.php');
            if (is_file($cacheFile)) {
                $cached = require $cacheFile;
                if (is_array($cached)) {
                    $config->preload($cached);
                }
            }

            return $config;
        });
        $this->singleton('logger', fn () => new Logger(
            $this->storagePath('logs'),
            (string) $this->config('app.log', 'daily')
        ));
        $this->singleton('router', fn () => new Router($this));
        $this->singleton('request', fn () => Request::capture());
        $this->singleton(Handler::class, fn () => new Handler($this));
        $this->singleton('db', fn () => new \Vayu\Database\ConnectionManager($this));
        $this->singleton('session', fn () => new \Vayu\Session\Session());
        $this->singleton('auth', fn () => new \Vayu\Auth\AuthManager($this));
        $this->singleton('cache', fn () => new \Vayu\Cache\CacheManager($this));
        $this->singleton('rate_limiter', fn () => new \Vayu\RateLimiting\RateLimiter(
            $this->make('cache')->store()
        ));
        $this->singleton('broadcast', function (): \Vayu\Realtime\Broadcaster {
            $ws = (array) $this->config('websocket', []);

            return new \Vayu\Realtime\Broadcaster(
                (string) ($ws['broadcast_host'] ?? '127.0.0.1'),
                (int) ($ws['broadcast_port'] ?? 2207),
                (float) ($ws['broadcast_timeout'] ?? 0.5),
            );
        });
        $this->singleton('events', function (): \Vayu\Events\Dispatcher {
            $dispatcher = new \Vayu\Events\Dispatcher();
            foreach ((array) $this->config('events', []) as $event => $listeners) {
                foreach ((array) $listeners as $listener) {
                    $dispatcher->listen($event, $listener);
                }
            }

            // Auto-broadcast: any dispatched event implementing BroadcastsEvents
            // is pushed to WebSocket clients via the broadcast bridge.
            if ($this->config('websocket.auto_broadcast', true)) {
                $dispatcher->listen('*', function (...$args): void {
                    $event = $args[0] ?? null;
                    if ($event instanceof \Vayu\Realtime\BroadcastsEvents) {
                        $this->make('broadcast')->dispatch($event);
                    }
                });
            }

            return $dispatcher;
        });

        date_default_timezone_set((string) $this->config('app.timezone', 'UTC'));
    }

    public static function getInstance(): ?Application
    {
        return static::$instance;
    }

    /* ----------------------------------------------------------------
     | Container
     | ---------------------------------------------------------------- */

    public function bind(string $key, callable $factory): void
    {
        $this->bindings[$key] = $factory;
    }

    public function singleton(string $key, callable $factory): void
    {
        $this->bindings[$key] = function () use ($key, $factory) {
            return $this->instances[$key] ??= $factory($this);
        };
    }

    public function instance(string $key, mixed $object): void
    {
        $this->instances[$key] = $object;
        $this->bindings[$key] = fn () => $object;
    }

    public function make(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            return ($this->bindings[$key])($this);
        }

        if (class_exists($key)) {
            return new $key();
        }

        throw new \RuntimeException("Nothing is bound in the container for [{$key}].");
    }

    public function bound(string $key): bool
    {
        return isset($this->bindings[$key]) || array_key_exists($key, $this->instances);
    }

    /* ----------------------------------------------------------------
     | Paths
     | ---------------------------------------------------------------- */

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path !== '' ? '/' . $path : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path !== '' ? '/' . $path : ''));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path !== '' ? '/' . $path : ''));
    }

    /* ----------------------------------------------------------------
     | Shortcuts
     | ---------------------------------------------------------------- */

    public function config(?string $key = null, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = $this->make('config');

        return $key === null ? $config : $config->get($key, $default);
    }

    public function router(): Router
    {
        return $this->make('router');
    }

    public function logger(): Logger
    {
        return $this->make('logger');
    }

    public function request(): Request
    {
        return $this->make('request');
    }

    public function isDebug(): bool
    {
        return (bool) $this->config('app.debug', false);
    }

    public function environment(): string
    {
        return (string) $this->config('app.env', 'production');
    }

    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /* ----------------------------------------------------------------
     | Lifecycle
     | ---------------------------------------------------------------- */

    public function bootExceptionHandling(): void
    {
        /** @var Handler $handler */
        $handler = $this->make(Handler::class);
        $handler->register();
    }

    public function run(): void
    {
        $request = $this->request();
        $response = $this->router()->dispatch($request);
        $response->send();
    }

    public function runConsole(array $argv): int
    {
        return (new ConsoleKernel($this))->handle($argv);
    }

    /* ----------------------------------------------------------------
     | Environment loading (works with or without vlucas/phpdotenv)
     | ---------------------------------------------------------------- */

    public static function loadEnvironment(string $basePath): void
    {
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($basePath)->safeLoad();

            return;
        }

        $envFile = $basePath . '/.env';
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    /* ----------------------------------------------------------------
     | Legacy compatibility (opt-in): bridge old array routes
     | ---------------------------------------------------------------- */

    public function registerLegacyRoutes(): void
    {
        if (!filter_var(\env('VAYU_LEGACY_ROUTES', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            $map = [];
            foreach (['app/view.php', 'api/gateway.php'] as $relative) {
                $file = $this->basePath($relative);
                if (is_file($file)) {
                    $routes = require $file;
                    if (is_array($routes)) {
                        $map += $routes;
                    }
                }
            }

            if ($map === []) {
                return;
            }

            foreach (['core/RouteProvider.php', 'core/BaseController.php', 'app/bridge/Welcome.php'] as $relative) {
                $file = $this->basePath($relative);
                if (is_file($file)) {
                    require_once $file;
                }
            }

            $router = $this->router();
            foreach ($map as $uri => $handler) {
                if (!is_array($handler) || count($handler) !== 2) {
                    continue;
                }

                $path = $uri === 'default' ? '/' : '/' . ltrim((string) $uri, '/');
                if ($router->has($path)) {
                    continue; // new routes always win
                }

                [$class, $method] = $handler;
                $router->any($path, function () use ($class, $method) {
                    if (!class_exists($class) || !method_exists($class, $method)) {
                        return \Vayu\Http\Response::make("Legacy controller {$class}::{$method} not found", 500);
                    }

                    ob_start();
                    $result = (new $class())->{$method}();
                    $buffered = (string) ob_get_clean();

                    if ($result instanceof \Vayu\Http\Response) {
                        return $result;
                    }

                    return \Vayu\Http\Response::make($buffered !== '' ? $buffered : (string) $result);
                });
            }
        } catch (\Throwable $e) {
            $this->logger()->error('Legacy route bridge failed: ' . $e->getMessage());
        }
    }
}
