<?php

namespace Vayu\Middleware;

use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Session\Session;

/**
 * Starts the session at the beginning of the request (and ages flash data).
 */
class StartSession implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var Session $session */
        $session = \app('session');
        $session->start();

        return $next($request);
    }
}
