<?php

namespace Vayu\Exceptions;

use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;

/**
 * Central error + exception handler.
 *
 * Converts PHP errors to exceptions, logs everything, and renders a pretty
 * debug page (when APP_DEBUG=true) or a clean error view otherwise.
 */
class Handler
{
    public function __construct(protected Application $app)
    {
    }

    public function register(): void
    {
        error_reporting(E_ALL);

        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if ((error_reporting() & $level) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        set_exception_handler(function (\Throwable $e): void {
            $this->renderFromGlobalScope($e);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->renderFromGlobalScope(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    protected function renderFromGlobalScope(\Throwable $e): void
    {
        try {
            $request = $this->app->request();
        } catch (\Throwable) {
            $request = null;
        }

        if ($this->app->runningInConsole()) {
            fwrite(STDERR, '[' . get_class($e) . '] ' . $e->getMessage() . PHP_EOL
                . $e->getFile() . ':' . $e->getLine() . PHP_EOL);

            return;
        }

        $this->render($request, $e)->send();
    }

    public function render(?Request $request, \Throwable $e): Response
    {
        if ($e instanceof \Vayu\Validation\ValidationException) {
            return $this->renderValidationException($request, $e);
        }

        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        $this->report($e, $status);

        $wantsJson = $request?->wantsJson() ?? false;

        if ($wantsJson) {
            return (new Response())->json($this->jsonPayload($e, $status), $status);
        }

        if ($this->app->isDebug() && $status >= 500) {
            return Response::make($this->renderDebugPage($e, $status), $status);
        }

        return Response::make($this->renderErrorView($status, $e), $status);
    }

    protected function renderValidationException(?Request $request, \Vayu\Validation\ValidationException $e): Response
    {
        if ($request?->wantsJson() ?? false) {
            return (new Response())->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        // Web: flash errors + old input and redirect back.
        try {
            /** @var \Vayu\Session\Session $session */
            $session = $this->app->make('session');
            $session->start();
            $session->flash('__errors', $e->errors());
            $session->flashInput($request?->input() ?? []);
        } catch (\Throwable) {
            // session unavailable — fall through to a plain redirect
        }

        $back = $request?->header('Referer') ?? '/';

        return (new Response())->redirect($back, 302);
    }

    protected function report(\Throwable $e, int $status): void
    {
        $level = $status >= 500 ? 'error' : 'warning';
        $this->app->logger()->log($level, sprintf(
            '%s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonPayload(\Throwable $e, int $status): array
    {
        $payload = [
            'status' => $status,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : $this->statusText($status),
        ];

        if ($this->app->isDebug()) {
            $payload['exception'] = get_class($e);
            $payload['file'] = $e->getFile() . ':' . $e->getLine();
        }

        return $payload;
    }

    protected function renderErrorView(int $status, \Throwable $e): string
    {
        $view = $this->app->basePath('resources/views/errors/' . $status . '.php');
        if (is_file($view)) {
            ob_start();
            (static function (string $__file, int $status, string $message): void {
                include $__file;
            })($view, $status, $this->statusText($status));

            return (string) ob_get_clean();
        }

        $title = $status . ' &middot; ' . $this->statusText($status);

        return "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            . "<title>{$title}</title></head>"
            . "<body style=\"font-family:system-ui,sans-serif;display:grid;place-items:center;height:100vh;margin:0;background:#0b0d12;color:#e6e8ee\">"
            . "<div style=\"text-align:center\"><h1 style=\"font-size:4rem;margin:0\">{$status}</h1>"
            . "<p style=\"opacity:.7\">{$this->statusText($status)}</p></div></body></html>";
    }

    protected function renderDebugPage(\Throwable $e, int $status): string
    {
        $class = htmlspecialchars(get_class($e));
        $message = htmlspecialchars($e->getMessage());
        $location = htmlspecialchars($e->getFile() . ':' . $e->getLine());
        $trace = htmlspecialchars($e->getTraceAsString());

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$status} &middot; {$class}</title>
<style>
  :root{color-scheme:dark}
  body{margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#0b0d12;color:#e6e8ee}
  .bar{background:#e5484d;color:#fff;padding:18px 28px}
  .bar small{opacity:.85;text-transform:uppercase;letter-spacing:.08em;font-size:11px}
  .bar h1{margin:.3rem 0 0;font-size:1.3rem;font-family:system-ui,sans-serif}
  .meta{padding:14px 28px;background:#151822;color:#9aa4b2;font-size:13px;border-bottom:1px solid #222838}
  pre{margin:0;padding:24px 28px;white-space:pre-wrap;word-break:break-word;font-size:12.5px;line-height:1.7;color:#c3c9d6}
</style>
</head>
<body>
  <div class="bar"><small>{$class} &middot; HTTP {$status}</small><h1>{$message}</h1></div>
  <div class="meta">{$location}</div>
  <pre>{$trace}</pre>
</body>
</html>
HTML;
    }

    protected function statusText(int $status): string
    {
        return [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ][$status] ?? 'Error';
    }
}
