<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class RunCommand extends Command
{
    protected static string $name = 'run';

    protected static string $description = 'Run the application on the PHP development server';

    public function handle(array $args): int
    {
        $host = '127.0.0.1';
        $port = '8000';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
            } elseif (str_starts_with($arg, '--port=')) {
                $port = substr($arg, 7);
            }
        }

        $docroot = $this->app->publicPath();
        $router = $this->app->publicPath('index.php');

        $this->info("Vayu development server started: http://{$host}:{$port}");
        $this->dim("Document root: {$docroot}");
        $this->dim('Press Ctrl+C to stop.');
        $this->line('');

        $command = sprintf(
            '%s -S %s:%s -t %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docroot),
            escapeshellarg($router)
        );

        passthru($command, $exitCode);

        return $exitCode;
    }
}
