<?php

/**
 * API routes.
 *
 * @var \Vayu\Routing\Router $router
 */

use App\Controllers\Api\AuthController;
use App\Middleware\ExampleMiddleware;
use Vayu\Http\Request;
use Vayu\Http\Response;

// Health check endpoint.
$router->get('/up', fn () => (new Response())->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'time' => date('c'),
]))->name('health');

// Token-based API authentication demo.
$router->post('/api/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

$router->get('/api/user', [AuthController::class, 'me'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('api.user');

$router->group(['prefix' => 'api', 'middleware' => [ExampleMiddleware::class]], function ($router): void {
    $router->get('/ping', fn () => (new Response())->json(['pong' => true]));

    $router->get('/echo/{value}', fn (Request $request, string $value) => (new Response())->json([
        'value' => $value,
        'query' => $request->query(),
    ]));
});
