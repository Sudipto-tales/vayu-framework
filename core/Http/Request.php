<?php

namespace Vayu\Http;

/**
 * Immutable snapshot of the incoming HTTP request.
 */
class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $request
     * @param array<string, string> $headers
     * @param array<string, mixed>  $server
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $request,
        public readonly array $headers,
        public readonly array $server,
        public readonly array $cookies,
        public readonly ?string $rawBody,
        public readonly array $files = [],
        public array $attributes = [],
    ) {
    }

    /**
     * Build a new request (or FormRequest subclass) from an existing one.
     */
    public static function createFrom(Request $from): static
    {
        return new static(
            $from->method,
            $from->path,
            $from->query,
            $from->request,
            $from->headers,
            $from->server,
            $from->cookies,
            $from->rawBody,
            $from->files,
            $from->attributes,
        );
    }

    public static function capture(): static
    {
        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $headers = self::readHeaders($server);
        $raw = file_get_contents('php://input');
        $raw = $raw === false ? '' : $raw;

        $body = $_POST;
        $contentType = $headers['Content-Type'] ?? '';
        if ($raw !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        // Method spoofing for HTML forms / APIs.
        if ($method === 'POST') {
            $override = $body['_method'] ?? ($headers['X-Http-Method-Override'] ?? null);
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        return new static(
            $method,
            self::resolvePath($server),
            $_GET,
            $body,
            $headers,
            $server,
            $_COOKIE,
            $raw,
            self::normalizeFiles($_FILES)
        );
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFile>
     */
    protected static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $file) {
            // Only single-file inputs are handled here.
            if (is_array($file) && isset($file['name']) && !is_array($file['name'])) {
                $normalized[$key] = UploadedFile::fromArray($file);
            }
        }

        return $normalized;
    }

    public function file(string $key): ?UploadedFile
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]->isValid();
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    protected static function readHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     */
    protected static function resolvePath(array $server): string
    {
        // Legacy ?route= support (old .htaccess rewrite target).
        if (!empty($_GET['route'])) {
            return '/' . trim((string) $_GET['route'], '/') ?: '/';
        }

        $uri = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = is_string($uri) ? rawurldecode($uri) : '/';

        $script = (string) ($server['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $scriptFile = basename($script);
        $trimmed = ltrim($uri, '/');
        if ($scriptFile !== '' && str_starts_with($trimmed, $scriptFile)) {
            $uri = substr($trimmed, strlen($scriptFile));
        }

        $uri = '/' . ltrim($uri, '/');

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->query, $this->request);

        if ($key === null) {
            return $all;
        }

        return $all[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $key))));

        return $this->headers[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', '') ?? '', '/json');
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '') ?? '';

        return $this->isJson()
            || str_contains($accept, '/json')
            || str_contains($accept, '+json');
    }

    public function ip(): ?string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                return trim(explode(',', (string) $this->server[$key])[0]);
            }
        }

        return null;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
}
