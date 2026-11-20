<?php

use App\Requests\ContactRequest;
use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\VerifyCsrfToken;
use Vayu\Session\Session;

return function (Assert $assert): void {
    $app = new Application(dirname(__DIR__));
    $session = new Session(native: false);
    $session->start();
    $app->instance('session', $session);

    $token = $session->token();
    $middleware = new VerifyCsrfToken();
    $next = fn (Request $r) => Response::make('passed');

    $make = static function (string $method, string $path, array $body = [], array $headers = []): Request {
        return new Request($method, $path, [], $body, $headers, [], [], null);
    };

    // GET skips verification
    $assert->equals('GET skips csrf', 'passed', $middleware->handle($make('GET', '/contact'), $next)->getContent());

    // POST with valid token passes
    $ok = $middleware->handle($make('POST', '/contact', ['_token' => $token]), $next);
    $assert->equals('POST valid token passes', 'passed', $ok->getContent());

    // POST with valid token via header passes
    $okHeader = $middleware->handle($make('POST', '/contact', [], ['X-Csrf-Token' => $token]), $next);
    $assert->equals('POST valid header token passes', 'passed', $okHeader->getContent());

    // POST with bad token -> 419
    $status = 0;
    try {
        $middleware->handle($make('POST', '/contact', ['_token' => 'wrong']), $next);
    } catch (\Vayu\Exceptions\HttpException $e) {
        $status = $e->getStatusCode();
    }
    $assert->equals('POST bad token -> 419', 419, $status);

    // Excluded api/* path skips verification
    $assert->equals('api path skips csrf', 'passed', $middleware->handle($make('POST', '/api/ping'), $next)->getContent());

    // FormRequest validation: invalid -> throws, valid -> validated()
    $invalid = ContactRequest::createFrom($make('POST', '/contact', ['name' => 'A', 'email' => 'bad']));
    $threw = false;
    try {
        $invalid->validateResolved();
    } catch (\Vayu\Validation\ValidationException) {
        $threw = true;
    }
    $assert->that('ContactRequest rejects invalid input', $threw);

    $valid = ContactRequest::createFrom($make('POST', '/contact', [
        'name' => 'Ada', 'email' => 'ada@x.io', 'message' => 'Hello there, this is long enough.',
    ]));
    $valid->validateResolved();
    $assert->equals('ContactRequest validated() returns data', 'Ada', $valid->validated()['name']);
};
