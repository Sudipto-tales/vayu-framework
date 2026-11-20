<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class CacheClearCommand extends Command
{
    protected static string $name = 'cache:clear';

    protected static string $description = 'Flush the application cache store';

    public function handle(array $args): int
    {
        $store = $args[0] ?? null;

        try {
            $this->app->make('cache')->store($store)->flush();
        } catch (\Throwable $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());

            return 1;
        }

        $this->info('Cache cleared' . ($store ? " ({$store})." : '.'));

        return 0;
    }
}
