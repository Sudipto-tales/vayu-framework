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

// Real-time demo: push a chat message from the HTTP process to WS clients.
// (Under api/* so it is CSRF-exempt; the WS server must be running.)
$router->post('/api/broadcast', function (Request $request) {
    $channel = (string) $request->input('channel', 'room.lobby');
    $text = (string) $request->input('text', 'Hello from the HTTP process');

    $delivered = broadcast()->event($channel, 'chat.message', [
        'id' => 'server',
        'text' => $text,
        'at' => date('c'),
    ]);

    return (new Response())->json([
        'queued' => $delivered,
        'channel' => $channel,
        'note' => $delivered ? 'Pushed to the WebSocket server.' : 'WebSocket server unreachable — is `php vayu websocket` running?',
    ]);
})->name('api.broadcast');

$router->group(['prefix' => 'api', 'middleware' => [ExampleMiddleware::class]], function ($router): void {
    $router->get('/ping', fn () => (new Response())->json(['pong' => true]));

    $router->get('/echo/{value}', fn (Request $request, string $value) => (new Response())->json([
        'value' => $value,
        'query' => $request->query(),
    ]));
});
