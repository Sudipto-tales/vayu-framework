<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * Clear all caches produced by `optimize` (and the runtime cache store).
 */
class OptimizeClearCommand extends Command
{
    protected static string $name = 'optimize:clear';

    protected static string $description = 'Clear config and application caches';

    public function handle(array $args): int
    {
        $this->comment('Clearing caches...');

        (new ConfigClearCommand($this->app))->handle($args);
        (new CacheClearCommand($this->app))->handle($args);

        $this->line('');
        $this->info('Caches cleared.');

        return 0;
    }
}
