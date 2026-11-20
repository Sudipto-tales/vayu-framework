<?php

namespace Vayu\Middleware;

use Vayu\Auth\AuthManager;
use Vayu\Exceptions\HttpException;
use Vayu\Http\Request;
use Vayu\Http\Response;

/**
 * Authenticate the request via a Bearer API token or an existing session.
 * Sets the resolved user on the auth manager and as a request attribute.
 */
class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var AuthManager $auth */
        $auth = \app('auth');

        $token = $request->bearerToken();
        if ($token !== null && $token !== '') {
            $user = $auth->userFromToken($token);
            if ($user !== null) {
                $auth->setUser($user);
                $request->setAttribute('user', $user);

                return $next($request);
            }
        }

        if ($auth->check()) {
            $request->setAttribute('user', $auth->user());

            return $next($request);
        }

        throw new HttpException(401, 'Unauthenticated.');
    }
}
