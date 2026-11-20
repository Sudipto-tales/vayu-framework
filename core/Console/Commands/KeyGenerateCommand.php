<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class KeyGenerateCommand extends Command
{
    protected static string $name = 'key:generate';

    protected static string $description = 'Generate and set the application key in .env';

    public function handle(array $args): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envPath = $this->app->basePath('.env');

        if (!is_file($envPath)) {
            $example = $this->app->basePath('.env.example');
            if (is_file($example)) {
                copy($example, $envPath);
                $this->comment('Created .env from .env.example');
            } else {
                file_put_contents($envPath, '');
            }
        }

        $contents = (string) file_get_contents($envPath);

        if (preg_match('/^APP_KEY=.*$/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents);
        } else {
            $contents = rtrim($contents, "\n") . "\nAPP_KEY=" . $key . "\n";
        }

        file_put_contents($envPath, $contents);

        $this->info('Application key set successfully.');
        $this->dim($key);

        return 0;
    }
}
