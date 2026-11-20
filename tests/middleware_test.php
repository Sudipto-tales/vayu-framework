<?php

use Vayu\Application;
use Vayu\Http\Request;
use Vayu\Http\Response;
use Vayu\Middleware\Pipeline;

return function (Assert $assert): void {
    $app = new Application(dirname(__DIR__));
    $request = new Request('GET', '/', [], [], [], [], [], null);

    $order = [];

    $response = (new Pipeline($app))
        ->send($request)
        ->through([
            function (Request $req, Closure $next) use (&$order): Response {
                $order[] = 'a:before';
                $res = $next($req);
                $order[] = 'a:after';

                return $res;
            },
            function (Request $req, Closure $next) use (&$order): Response {
                $order[] = 'b:before';
                $res = $next($req);
                $order[] = 'b:after';

                return $res;
            },
        ])
        ->then(function (Request $req) use (&$order): Response {
            $order[] = 'handler';

            return Response::make('done');
        });

    $assert->equals('pipeline returns handler result', 'done', $response->getContent());
    $assert->equals(
        'onion order is correct',
        'a:before,b:before,handler,b:after,a:after',
        implode(',', $order)
    );

    // Short-circuit: middleware returns early without calling $next.
    $short = (new Pipeline($app))
        ->send($request)
        ->through([
            fn (Request $req, Closure $next) => Response::make('blocked', 403),
        ])
        ->then(fn (Request $req) => Response::make('should not run'));

    $assert->equals('short-circuit status', 403, $short->getStatus());
    $assert->equals('short-circuit body', 'blocked', $short->getContent());
};
