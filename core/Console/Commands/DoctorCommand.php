<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * Environment & health diagnostics.
 */
class DoctorCommand extends Command
{
    protected static string $name = 'doctor';

    protected static string $description = 'Run a full system health check';

    public function handle(array $args): int
    {
        $this->line('');
        $this->comment('  Vayu Doctor');
        $this->line('');

        $failures = 0;

        // PHP version
        $failures += $this->check(
            'PHP >= 8.1',
            version_compare(PHP_VERSION, '8.1.0', '>='),
            PHP_VERSION
        );

        // Required extensions
        foreach (['pdo', 'mbstring', 'json', 'openssl'] as $ext) {
            $failures += $this->check("ext-{$ext}", extension_loaded($ext), '');
        }

        // Optional extensions (warn only)
        foreach (['curl', 'pdo_sqlite', 'pdo_mysql'] as $ext) {
            $this->check("ext-{$ext} (optional)", extension_loaded($ext), '', true);
        }

        // .env present
        $failures += $this->check('.env file', is_file($this->app->basePath('.env')), 'copy .env.example');

        // APP_KEY set
        $failures += $this->check(
            'APP_KEY set',
            (bool) $this->app->config('app.key'),
            'run: php vayu key:generate'
        );

        // Composer dependencies
        $this->check(
            'composer deps (optional)',
            is_file($this->app->basePath('vendor/autoload.php')),
            'run: composer install',
            true
        );

        // Writable storage directories
        foreach (['storage/logs', 'storage/cache', 'storage/uploads'] as $dir) {
            $path = $this->app->basePath($dir);
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $failures += $this->check("writable {$dir}", is_dir($path) && is_writable($path), '');
        }

        // Public front controller
        $failures += $this->check(
            'public/index.php',
            is_file($this->app->publicPath('index.php')),
            ''
        );

        $this->line('');
        if ($failures === 0) {
            $this->info('  All checks passed. ✔');
        } else {
            $this->error("  {$failures} check(s) failed.");
        }
        $this->line('');

        return $failures === 0 ? 0 : 1;
    }

    protected function check(string $label, bool $passed, string $hint = '', bool $warnOnly = false): int
    {
        if ($passed) {
            $mark = "\033[32m✔\033[0m";
        } elseif ($warnOnly) {
            $mark = "\033[33m•\033[0m";
        } else {
            $mark = "\033[31m✘\033[0m";
        }

        $suffix = (!$passed && $hint !== '') ? "  \033[2m({$hint})\033[0m" : '';
        $this->line(sprintf('  %s  %-28s%s', $mark, $label, $suffix));

        return (!$passed && !$warnOnly) ? 1 : 0;
    }
}
