<?php

namespace Vayu\Middleware;

use Vayu\Http\Request;
use Vayu\Http\Response;

/**
 * Class-based middleware contract.
 *
 * Call `$next($request)` to pass control down the pipeline; return a Response
 * early to short-circuit it.
 */
interface MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response;
}
