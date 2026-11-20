<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Database\Migrations\Migrator;

class MigrateStatusCommand extends Command
{
    protected static string $name = 'migrate:status';

    protected static string $description = 'Show the status of each migration';

    public function handle(array $args): int
    {
        $migrator = new Migrator($this->app->basePath('database/migrations'));
        $status = $migrator->status();

        if ($status === []) {
            $this->comment('No migrations found.');

            return 0;
        }

        $rows = [];
        foreach ($status as $name => $info) {
            $rows[] = [
                $info['ran'] ? 'Ran' : 'Pending',
                $info['batch'] !== null ? (string) $info['batch'] : '-',
                $name,
            ];
        }

        $this->line('');
        $this->table(['STATUS', 'BATCH', 'MIGRATION'], $rows);
        $this->line('');

        return 0;
    }
}
