<?php

/**
 * Create and configure the Vayu application instance.
 *
 * Both the HTTP front controller (public/index.php) and the CLI entry point
 * (bin/vayu) require this file to obtain a fully wired application.
 */

use Vayu\Application;

define('VAYU_START', microtime(true));

/** @var string $basePath */
$basePath = require __DIR__ . '/autoload.php';

Application::loadEnvironment($basePath);

$app = new Application($basePath);

// Register error/exception handling as early as possible.
$app->bootExceptionHandling();

// Route definitions. `$router` is in scope for the required files.
$router = $app->router();

// Global middleware for every request: start the session, verify CSRF.
$router->globalMiddleware([
    \Vayu\Middleware\StartSession::class,
    \Vayu\Middleware\VerifyCsrfToken::class,
]);

// Route middleware aliases (usable as "throttle:60,1", "auth").
$router->aliasMiddleware('throttle', \Vayu\Middleware\ThrottleRequests::class);
$router->aliasMiddleware('auth', \Vayu\Middleware\Authenticate::class);

require $basePath . '/routes/web.php';
require $basePath . '/routes/api.php';

// Optional strangler-fig bridge for the old array-based routes.
$app->registerLegacyRoutes();

return $app;
