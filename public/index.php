<?php

/**
 * Vayu HTTP front controller.
 *
 * The web server's document root should point here (public/).
 */

// When using `php -S` / `php vayu serve`, serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

/** @var \Vayu\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->run();
