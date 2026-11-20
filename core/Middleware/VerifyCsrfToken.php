<?php

namespace Vayu\Middleware;

use Vayu\Exceptions\HttpException;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Session\Session;

/**
 * Verifies the CSRF token on state-changing requests.
 *
 * Token is read from the `_token` form field or the X-CSRF-TOKEN /
 * X-XSRF-TOKEN header. Read methods and excluded paths are skipped.
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * URI prefixes exempt from CSRF verification.
     *
     * @var list<string>
     */
    protected array $except = ['api', 'up'];

    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        /** @var Session $session */
        $session = \app('session');

        if (!hash_equals($session->token(), (string) $this->tokenFromRequest($request))) {
            throw new HttpException(419, 'CSRF token mismatch.');
        }

        return $next($request);
    }

    protected function shouldSkip(Request $request): bool
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $path = ltrim($request->path, '/');
        foreach ($this->except as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function tokenFromRequest(Request $request): ?string
    {
        $token = $request->input('_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-XSRF-TOKEN');
    }
}
