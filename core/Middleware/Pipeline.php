<?php

namespace Vayu\Middleware;

use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;

/**
 * Onion-style middleware pipeline.
 *
 * Middleware may be:
 *  - a class name implementing MiddlewareInterface
 *  - a class name with a `handle(Request, Closure): Response` method
 *  - a Closure: fn(Request $request, Closure $next): Response
 */
class Pipeline
{
    protected Request $request;

    /** @var list<string|\Closure|object> */
    protected array $pipes = [];

    /** @var array<string, class-string> */
    protected array $aliases = [];

    public function __construct(protected Application $app)
    {
    }

    public function send(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param list<string|\Closure|object> $pipes
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * @param array<string, class-string> $aliases
     */
    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;

        return $this;
    }

    public function then(\Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            function (\Closure $next, string|object $pipe): \Closure {
                return function (Request $request) use ($next, $pipe): Response {
                    $handler = $this->resolve($pipe);

                    return $handler($request, $next);
                };
            },
            function (Request $request) use ($destination): Response {
                return $destination($request);
            }
        );

        return $pipeline($this->request);
    }

    /**
     * @return callable(Request, \Closure): Response
     */
    protected function resolve(string|object $pipe): callable
    {
        if ($pipe instanceof \Closure) {
            return $pipe;
        }

        if (is_object($pipe)) {
            return $this->asHandler($pipe, get_class($pipe));
        }

        // String form, optionally "alias:param1,param2".
        [$name, $paramString] = array_pad(explode(':', $pipe, 2), 2, '');
        $class = $this->aliases[$name] ?? $name;
        $params = $paramString === '' ? [] : explode(',', $paramString);

        $instance = $this->instantiate($class, $params);

        return $this->asHandler($instance, $class);
    }

    /**
     * @param list<string> $params
     */
    protected function instantiate(string $class, array $params): object
    {
        if (method_exists($class, 'fromParams')) {
            return $class::fromParams($params);
        }

        if ($params !== []) {
            return new $class(...$params);
        }

        return $this->app->make($class);
    }

    /**
     * @return callable(Request, \Closure): Response
     */
    protected function asHandler(object $instance, string $name): callable
    {
        if ($instance instanceof MiddlewareInterface || method_exists($instance, 'handle')) {
            return [$instance, 'handle'];
        }

        throw new \RuntimeException("Middleware [{$name}] must implement handle(Request, Closure).");
    }
}
