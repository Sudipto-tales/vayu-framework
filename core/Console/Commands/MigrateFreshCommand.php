<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Database\Migrations\Migrator;

class MigrateFreshCommand extends Command
{
    protected static string $name = 'migrate:fresh';

    protected static string $description = 'Drop all tables and re-run every migration';

    public function handle(array $args): int
    {
        $migrator = new Migrator($this->app->basePath('database/migrations'));

        try {
            $count = $migrator->fresh();
        } catch (\Throwable $e) {
            $this->error('Fresh migration failed: ' . $e->getMessage());

            return 1;
        }

        foreach ($migrator->log as $line) {
            $this->line('  ' . $line);
        }

        $this->info("Database refreshed. Migrated {$count} migration(s).");

        return 0;
    }
}
