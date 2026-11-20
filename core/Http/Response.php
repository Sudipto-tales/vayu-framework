<?php

namespace Vayu\Http;

/**
 * A simple HTTP response value object.
 */
class Response
{
    protected string $content;

    protected int $status;

    /** @var array<string, string> */
    protected array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    public function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $this->content = (string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        $this->status = $status;
        $this->headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);

        return $this;
    }

    public function created(mixed $data, array $headers = []): static
    {
        return $this->json($data, 201, $headers);
    }

    public function accepted(mixed $data, array $headers = []): static
    {
        return $this->json($data, 202, $headers);
    }

    public function noContent(int $status = 204): static
    {
        $this->content = '';
        $this->status = $status;

        return $this;
    }

    public function html(string $content, int $status = 200): static
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers['Content-Type'] ??= 'text/html; charset=utf-8';

        return $this;
    }

    public function redirect(string $to, int $status = 302): static
    {
        $this->content = '';
        $this->status = $status;
        $this->headers['Location'] = $to;

        return $this;
    }

    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            $this->headers['Content-Type'] ??= 'text/html; charset=utf-8';
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->content;
    }
}
