<?php

use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;

/*
|--------------------------------------------------------------------------
| Global helper functions
|--------------------------------------------------------------------------
| New framework helpers plus legacy shims (env, base_url, load_view, api_*)
| so existing views and controllers keep working during migration.
*/

// Reuse legacy api_* / view_data / render_view helpers (guarded internally).
$legacyHelpers = __DIR__ . '/../Helpers.php';
if (is_file($legacyHelpers)) {
    require_once $legacyHelpers;
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return match (strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $value,
            };
        }

        return $default;
    }
}

if (!function_exists('app')) {
    function app(?string $key = null): mixed
    {
        $app = Application::getInstance();

        if ($app === null) {
            throw new RuntimeException('The application has not been bootstrapped.');
        }

        return $key === null ? $app : $app->make($key);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return app()->config($key, $default);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return app()->request();
    }
}

if (!function_exists('response')) {
    function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        return Response::make($content, $status, $headers);
    }
}

if (!function_exists('logger')) {
    function logger(): \Vayu\Logging\Logger
    {
        return app()->logger();
    }
}

if (!function_exists('json')) {
    function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return (new Response())->json($data, $status, $headers);
    }
}

if (!function_exists('auth')) {
    function auth(): \Vayu\Auth\AuthManager
    {
        return app('auth');
    }
}

if (!function_exists('cache')) {
    function cache(): \Vayu\Cache\Repository
    {
        return app('cache')->store();
    }
}

if (!function_exists('event')) {
    /**
     * @param array<int, mixed> $payload
     * @return list<mixed>
     */
    function event(object|string $event, array $payload = []): array
    {
        return app('events')->dispatch($event, $payload);
    }
}

if (!function_exists('broadcast')) {
    /**
     * Push a real-time event to WebSocket clients, or get the Broadcaster.
     *
     *   broadcast('room.1', 'chat.message', ['text' => 'hi']);
     *   broadcast()->to($connectionId, 'notice', [...]);
     *   broadcast()->dispatch($eventObject);
     *
     * @param array<string, mixed> $data
     */
    function broadcast(?string $channel = null, ?string $event = null, array $data = []): \Vayu\Realtime\Broadcaster
    {
        /** @var \Vayu\Realtime\Broadcaster $broadcaster */
        $broadcaster = app('broadcast');

        if ($channel !== null) {
            $broadcaster->event($channel, (string) $event, $data);
        }

        return $broadcaster;
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): Response
    {
        $base = app()->basePath();
        $candidates = [
            $base . '/resources/views/' . str_replace('.', '/', $name) . '.php',
            $base . '/app/page/' . $name . '.php', // legacy location
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                $render = static function (string $__file, array $__data): string {
                    extract($__data, EXTR_SKIP);
                    ob_start();
                    include $__file;

                    return (string) ob_get_clean();
                };

                return Response::make($render($file, $data));
            }
        }

        return Response::make("View [{$name}] not found.", 500);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): ?string
    {
        return app()->router()->url($name, $params);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): Response
    {
        return (new Response())->redirect($to, $status);
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $configured = app('app')->config('app.url');
        if (is_string($configured) && $configured !== '' && PHP_SAPI !== 'cli-server') {
            return rtrim($configured, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $base = $scheme . '://' . $host . $dir;

        return rtrim($base, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('load_view')) {
    // Legacy helper: echoes a view directly (kept for old controllers).
    function load_view(string $path, array $data = []): void
    {
        $file = app()->basePath($path);
        if (is_file($file)) {
            extract($data, EXTR_SKIP);
            require $file;
        } else {
            echo "Error: View '{$path}' not found!";
        }
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \Vayu\Exceptions\HttpException($status, $message);
    }
}

if (!function_exists('session')) {
    function session(): \Vayu\Session\Session
    {
        return app('session');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return session()->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        try {
            return session()->old($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('errors')) {
    /** @return array<string, list<string>> */
    function errors(): array
    {
        try {
            return session()->get('__errors', []);
        } catch (\Throwable) {
            return [];
        }
    }
}

if (!function_exists('error')) {
    function error(string $key): ?string
    {
        return errors()[$key][0] ?? null;
    }
}

if (!function_exists('back')) {
    function back(int $status = 302): \Vayu\Http\Response
    {
        $to = request()->header('Referer') ?? '/';

        return redirect($to, $status);
    }
}
