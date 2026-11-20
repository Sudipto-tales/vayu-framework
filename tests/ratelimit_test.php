<?php

use Vayu\Application;
use Vayu\Cache\ArrayStore;
use Vayu\Cache\Repository;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\ThrottleRequests;
use Vayu\RateLimiting\RateLimiter;

return function (Assert $assert): void {
    $limiter = new RateLimiter(new Repository(new ArrayStore()));
    $key = 'test-key';

    $assert->equals('starts at 0 attempts', 0, $limiter->attempts($key));
    $assert->equals('first hit -> 1', 1, $limiter->hit($key, 60));
    $assert->equals('second hit -> 2', 2, $limiter->hit($key, 60));
    $assert->equals('remaining of 5', 3, $limiter->remaining($key, 5));
    $assert->that('not too many yet (max 5)', !$limiter->tooManyAttempts($key, 5));

    $limiter->hit($key, 60);
    $limiter->hit($key, 60);
    $limiter->hit($key, 60); // now 5
    $assert->that('too many at 5/5', $limiter->tooManyAttempts($key, 5));
    $assert->that('availableIn > 0', $limiter->availableIn($key) > 0);

    $limiter->clear($key);
    $assert->equals('cleared resets to 0', 0, $limiter->attempts($key));

    // Through the middleware
    $app = new Application(dirname(__DIR__));
    $app->instance('rate_limiter', new RateLimiter(new Repository(new ArrayStore())));
    $throttle = ThrottleRequests::fromParams(['2', '1']); // 2 per minute
    $next = fn (Request $r) => Response::make('ok');
    $req = new Request('GET', '/api/thing', [], [], ['Accept' => 'application/json'], ['REMOTE_ADDR' => '10.0.0.1'], [], null);

    $r1 = $throttle->handle($req, $next);
    $assert->equals('1st request allowed', 200, $r1->getStatus());
    $assert->equals('rate-limit header present', '2', $r1->getHeaders()['X-RateLimit-Limit'] ?? null);

    $r2 = $throttle->handle($req, $next);
    $assert->equals('2nd request allowed', 200, $r2->getStatus());

    $r3 = $throttle->handle($req, $next);
    $assert->equals('3rd request blocked -> 429', 429, $r3->getStatus());
    $assert->that('Retry-After set', ($r3->getHeaders()['Retry-After'] ?? null) !== null);
};
