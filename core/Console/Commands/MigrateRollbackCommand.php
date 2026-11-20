<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Database\Migrations\Migrator;

class MigrateRollbackCommand extends Command
{
    protected static string $name = 'migrate:rollback';

    protected static string $description = 'Roll back the last batch of migrations';

    public function handle(array $args): int
    {
        $steps = 1;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--step=')) {
                $steps = max(1, (int) substr($arg, 7));
            }
        }

        $migrator = new Migrator($this->app->basePath('database/migrations'));

        try {
            $count = $migrator->rollback($steps);
        } catch (\Throwable $e) {
            $this->error('Rollback failed: ' . $e->getMessage());

            return 1;
        }

        foreach ($migrator->log as $line) {
            $this->line('  ' . $line);
        }

        $this->info($count === 0 ? 'Nothing to roll back.' : "Rolled back {$count} migration(s).");

        return 0;
    }
}
