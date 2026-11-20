<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    protected static string $name = 'migrate';

    protected static string $description = 'Run all pending database migrations';

    public function handle(array $args): int
    {
        $migrator = new Migrator($this->app->basePath('database/migrations'));

        try {
            $count = $migrator->run();
        } catch (\Throwable $e) {
            $this->error('Migration failed: ' . $e->getMessage());

            return 1;
        }

        foreach ($migrator->log as $line) {
            $this->line('  ' . $line);
        }

        $this->info($count === 0 ? 'Nothing to migrate.' : "Migrated {$count} migration(s).");

        return 0;
    }
}
