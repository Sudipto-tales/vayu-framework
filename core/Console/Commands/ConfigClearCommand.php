<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * Remove the compiled config cache so config is read from files again.
 */
class ConfigClearCommand extends Command
{
    protected static string $name = 'config:clear';

    protected static string $description = 'Remove the compiled config cache';

    public function handle(array $args): int
    {
        $cacheFile = $this->app->storagePath('cache/config.php');

        if (is_file($cacheFile)) {
            @unlink($cacheFile);
            $this->info('Configuration cache cleared.');
        } else {
            $this->dim('No configuration cache to clear.');
        }

        return 0;
    }
}
