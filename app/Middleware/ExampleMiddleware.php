<?php

namespace App\Middleware;

use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\MiddlewareInterface;

/**
 * Example middleware. Demonstrates the pipeline: it adds a response header
 * after the downstream handler runs. Copy this as a template.
 */
class ExampleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        return $response->header('X-Powered-By', 'Vayu');
    }
}
