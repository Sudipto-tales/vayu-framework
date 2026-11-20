<?php

namespace Vayu\Middleware;

use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\RateLimiting\RateLimiter;

/**
 * Throttle requests by IP + path.
 *
 * Usage on a route: ->middleware('throttle:60,1')   // 60 per 1 minute
 * or:               ->middleware(ThrottleRequests::perMinute(60))
 */
class ThrottleRequests implements MiddlewareInterface
{
    public function __construct(
        protected int $maxAttempts = 60,
        protected int $decayMinutes = 1,
    ) {
    }

    public static function perMinute(int $maxAttempts): self
    {
        return new self($maxAttempts, 1);
    }

    /**
     * @param list<string> $params
     */
    public static function fromParams(array $params): self
    {
        return new self((int) ($params[0] ?? 60), (int) ($params[1] ?? 1));
    }

    public function handle(Request $request, \Closure $next): Response
    {
        /** @var RateLimiter $limiter */
        $limiter = \app('rate_limiter');

        $key = $this->resolveKey($request);
        $decay = $this->decayMinutes * 60;

        if ($limiter->tooManyAttempts($key, $this->maxAttempts)) {
            return $this->tooManyResponse($request, $limiter->availableIn($key));
        }

        $limiter->hit($key, $decay);

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->header('X-RateLimit-Remaining', (string) $limiter->remaining($key, $this->maxAttempts));
    }

    protected function resolveKey(Request $request): string
    {
        $user = $request->attribute('user');
        $identity = is_array($user) && isset($user['id']) ? 'uid:' . $user['id'] : 'ip:' . $request->ip();

        return sha1($identity . '|' . $request->path);
    }

    protected function tooManyResponse(Request $request, int $retryAfter): Response
    {
        $headers = [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => '0',
        ];

        if ($request->wantsJson()) {
            return (new Response())->json(['message' => 'Too Many Requests.'], 429, $headers);
        }

        return Response::make('Too Many Requests', 429, $headers);
    }
}
