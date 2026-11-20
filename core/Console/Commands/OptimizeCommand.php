<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * Prepare the application for production by building its caches.
 *
 * Currently this compiles the config cache. Route caching is intentionally not
 * included: Vayu routes are defined with closures, which cannot be serialized.
 */
class OptimizeCommand extends Command
{
    protected static string $name = 'optimize';

    protected static string $description = 'Cache config (and other artifacts) for production';

    public function handle(array $args): int
    {
        $this->comment('Optimizing Vayu...');

        $status = (new ConfigCacheCommand($this->app))->handle($args);
        if ($status !== 0) {
            return $status;
        }

        $this->line('');
        $this->info('Optimization complete.');

        return 0;
    }
}
