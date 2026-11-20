<?php

namespace Vayu\Routing;

use Vayu\Application;
use Vayu\Exceptions\HttpException;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\Pipeline;

/**
 * HTTP router: dynamic params, named routes, method constraints, groups and a
 * middleware pipeline.
 */
class Router
{
    /** @var list<Route> */
    protected array $routes = [];

    /** @var array<string, Route> */
    protected array $named = [];

    /** @var list<string|\Closure> */
    protected array $globalMiddleware = [];

    /** @var array<string, class-string> */
    protected array $aliases = [];

    /** @var array{prefix: string, middleware: list<string|\Closure>} */
    protected array $groupStack = ['prefix' => '', 'middleware' => []];

    public function __construct(protected Application $app)
    {
    }

    public function get(string $path, mixed $handler): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->addRoute(['POST'], $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->addRoute(['PUT'], $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->addRoute(['PATCH'], $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->addRoute(['DELETE'], $path, $handler);
    }

    public function any(string $path, mixed $handler): Route
    {
        return $this->addRoute(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }

    /**
     * @param list<string> $methods
     */
    public function match(array $methods, string $path, mixed $handler): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $path, $handler);
    }

    /**
     * @param array{prefix?: string, middleware?: string|list<string|\Closure>} $attributes
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $previous = $this->groupStack;

        $this->groupStack = [
            'prefix' => $previous['prefix'] . $this->normalizePrefix($attributes['prefix'] ?? ''),
            'middleware' => array_merge($previous['middleware'], (array) ($attributes['middleware'] ?? [])),
        ];

        $callback($this);

        $this->groupStack = $previous;
    }

    /**
     * @param string|\Closure|list<string|\Closure> $middleware
     */
    public function globalMiddleware(string|\Closure|array $middleware): void
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, (array) $middleware);
    }

    /**
     * Register a short name for a middleware class, usable as "name" or
     * "name:param1,param2" on routes.
     *
     * @param class-string $class
     */
    public function aliasMiddleware(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    protected function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');

        return $prefix === '' ? '' : '/' . $prefix;
    }

    /**
     * @param list<string> $methods
     */
    protected function addRoute(array $methods, string $path, mixed $handler): Route
    {
        $path = $this->groupStack['prefix'] . '/' . ltrim($path, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $route = new Route($methods, $path, $handler);
        $route->middleware($this->groupStack['middleware']);

        $this->routes[] = $route;

        return $route;
    }

    public function has(string $path): bool
    {
        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $route) {
            if ($route->path === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): ?string
    {
        $this->indexNames();

        return isset($this->named[$name]) ? $this->named[$name]->url($params) : null;
    }

    protected function indexNames(): void
    {
        foreach ($this->routes as $route) {
            if ($route->name !== null) {
                $this->named[$route->name] = $route;
            }
        }
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        try {
            $route = $this->findRoute($request);

            $pipeline = (new Pipeline($this->app))->setAliases($this->aliases);

            return $pipeline
                ->send($request)
                ->through(array_merge($this->globalMiddleware, $route->middleware))
                ->then(fn (Request $req) => $this->runHandler($route, $req));
        } catch (\Throwable $e) {
            /** @var \Vayu\Exceptions\Handler $handler */
            $handler = $this->app->make(\Vayu\Exceptions\Handler::class);

            return $handler->render($request, $e);
        }
    }

    protected function findRoute(Request $request): Route
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if ($route->matches($request->method, $request->path)) {
                return $route;
            }

            if (preg_match('#^' . preg_quote($route->path, '#') . '/?$#', $request->path)) {
                $pathMatched = true;
            }
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Method Not Allowed');
        }

        throw new HttpException(404, 'Not Found');
    }

    protected function runHandler(Route $route, Request $request): Response
    {
        $handler = $route->handler;

        if (is_string($handler) && str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
        }

        if ($handler instanceof \Closure) {
            $result = $this->callWithParams($handler, $route->parameters, $request);
        } elseif (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : $this->app->make($class);
            $result = $this->callWithParams([$instance, $method], $route->parameters, $request);
        } else {
            throw new HttpException(500, 'Invalid route handler.');
        }

        return $this->toResponse($result);
    }

    /**
     * Resolve callable arguments: inject the Request by type-hint, route params
     * by name, then fall back to defaults.
     *
     * @param array<string, string> $params
     */
    protected function callWithParams(callable $callable, array $params, Request $request): mixed
    {
        $reflection = is_array($callable)
            ? new \ReflectionMethod($callable[0], $callable[1])
            : new \ReflectionFunction($callable);

        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName !== null && is_a($typeName, Request::class, true)) {
                if ($typeName === Request::class) {
                    $args[] = $request;
                } else {
                    // FormRequest subclass: build from the current request and
                    // run its validation before injecting.
                    $form = $typeName::createFrom($request);
                    if (method_exists($form, 'validateResolved')) {
                        $form->validateResolved();
                    }
                    $args[] = $form;
                }
            } elseif (array_key_exists($parameter->getName(), $params)) {
                $args[] = $params[$parameter->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $args[] = null;
            } else {
                $args[] = null;
            }
        }

        return $callable(...$args);
    }

    protected function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return (new Response())->json($result);
        }

        return Response::make((string) $result);
    }
}
