<?php

namespace Vayu\Routing;

/**
 * A single compiled route.
 */
class Route
{
    public ?string $name = null;

    /** @var list<string|\Closure> */
    public array $middleware = [];

    /** @var array<string, string> */
    public array $parameters = [];

    protected string $regex;

    /**
     * @param list<string>          $methods
     * @param array|\Closure|string $handler  [Class, method] | Closure | "Class@method"
     */
    public function __construct(
        public array $methods,
        public string $path,
        public mixed $handler,
    ) {
        $this->regex = $this->compile($path);
    }

    protected function compile(string $path): string
    {
        $regex = preg_replace_callback(
            '#/\{(\w+)(\?)?\}#',
            static function (array $m): string {
                return ($m[2] ?? '') === '?'
                    ? '(?:/(?P<' . $m[1] . '>[^/]+))?'
                    : '/(?P<' . $m[1] . '>[^/]+)';
            },
            $path
        );

        return '#^' . ($regex === '/' ? '/' : rtrim((string) $regex, '/')) . '/?$#';
    }

    public function matches(string $method, string $path): bool
    {
        if (!in_array($method, $this->methods, true)) {
            return false;
        }

        if (!preg_match($this->regex, $path, $matches)) {
            return false;
        }

        $this->parameters = array_filter(
            $matches,
            static fn ($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );

        return true;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string|\Closure|array<string|\Closure> $middleware
     */
    public function middleware(string|\Closure|array $middleware): static
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);

        return $this;
    }

    /**
     * Build a URL for this route, substituting parameters.
     *
     * @param array<string, string|int> $params
     */
    public function url(array $params = []): string
    {
        return preg_replace_callback(
            '#/\{(\w+)(\?)?\}#',
            static function (array $m) use ($params): string {
                unset($m[2]);
                if (isset($params[$m[1]])) {
                    return '/' . rawurlencode((string) $params[$m[1]]);
                }

                return ''; // drop optional / missing segment
            },
            $this->path
        ) ?: $this->path;
    }
}
