<?php

/**
 * Vayu framework autoloader.
 *
 * Works with OR without `composer install`:
 *  - If a Composer autoloader exists it is loaded first (for third-party deps
 *    such as phpmailer / phpdotenv).
 *  - A lightweight PSR-4 autoloader is always registered for the framework
 *    (`Vayu\`) and application (`App\`) namespaces so the app boots on a bare
 *    checkout.
 *
 * Returns the project base path.
 */

$basePath = dirname(__DIR__);

$composer = $basePath . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
}

spl_autoload_register(static function (string $class) use ($basePath): void {
    static $prefixes = [
        'Vayu\\' => '/core/',
        'App\\'  => '/app/',
    ];

    foreach ($prefixes as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $basePath . $dir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }

        return;
    }
});

require_once $basePath . '/core/Support/helpers.php';

return $basePath;
