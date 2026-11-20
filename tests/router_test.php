<?php

use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Routing\Router;

return function (Assert $assert): void {
    $app = new Application(dirname(__DIR__));
    $router = new Router($app);

    $router->get('/', fn () => Response::make('home'))->name('home');
    $router->get('/user/{id}', fn (Request $r, string $id) => (new Response())->json(['id' => $id]));
    $router->get('/post/{slug?}', fn (Request $r, string $slug = 'none') => Response::make($slug));
    $router->post('/user', fn () => Response::make('created'));

    $make = static function (string $method, string $path): Request {
        return new Request($method, $path, [], [], [], [], [], null);
    };

    // Static route
    $assert->equals('GET / -> home', 'home', $router->dispatch($make('GET', '/'))->getContent());

    // Required param + Request injection + JSON
    $res = $router->dispatch($make('GET', '/user/42'));
    $assert->equals('GET /user/42 status', 200, $res->getStatus());
    $assert->that('GET /user/42 body has id 42', str_contains($res->getContent(), '"id":"42"'));

    // Optional param present / absent
    $assert->equals('GET /post/hi -> hi', 'hi', $router->dispatch($make('GET', '/post/hi'))->getContent());
    $assert->equals('GET /post -> default', 'none', $router->dispatch($make('GET', '/post'))->getContent());

    // Method constraint: GET on a POST-only route -> 405
    $assert->equals('GET /user -> 405', 405, $router->dispatch($make('GET', '/user'))->getStatus());

    // Unknown route -> 404
    $assert->equals('GET /nope -> 404', 404, $router->dispatch($make('GET', '/nope'))->getStatus());

    // Named route URL generation
    $assert->equals('route(home)', '/', $router->url('home'));

    // has()
    $assert->that('has(/) is true', $router->has('/'));
    $assert->that('has(/missing) is false', !$router->has('/missing'));
};
