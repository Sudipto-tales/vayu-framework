<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

/**
 * One-shot project bootstrap: env, app key, storage dirs, migrations.
 *
 *   php vayu setup            # env + key + storage + migrate
 *   php vayu setup --seed     # also run the database seeders
 *   php vayu setup --fresh    # drop all tables and migrate from scratch
 */
class SetupCommand extends Command
{
    protected static string $name = 'setup';

    protected static string $description = 'First-time setup: env, app key, storage, migrations';

    public function handle(array $args): int
    {
        $seed = in_array('--seed', $args, true);
        $fresh = in_array('--fresh', $args, true);

        $this->line('');
        $this->comment('  Setting up Vayu...');
        $this->line('');

        // 1. Environment file + application key.
        $this->step('Environment & application key');
        if (!$this->app->config('app.key')) {
            (new KeyGenerateCommand($this->app))->handle([]);
        } else {
            $this->dim('  .env present, APP_KEY already set.');
        }

        // 2. Storage directories.
        $this->step('Storage directories');
        foreach (['storage/logs', 'storage/cache', 'storage/cache/data', 'storage/uploads'] as $dir) {
            $path = $this->app->basePath($dir);
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
                $this->dim("  created {$dir}");
            } else {
                $this->dim("  ok {$dir}");
            }
        }

        // 3. Migrations.
        $this->step($fresh ? 'Fresh migration' : 'Migrations');
        $migrateExit = $fresh
            ? (new MigrateFreshCommand($this->app))->handle([])
            : (new MigrateCommand($this->app))->handle([]);
        if ($migrateExit !== 0) {
            $this->error('  Migrations failed — check your database configuration.');

            return 1;
        }

        // 4. Seeders (optional).
        if ($seed) {
            $this->step('Seeding');
            (new DbSeedCommand($this->app))->handle([]);
        }

        $this->line('');
        $this->info('  Setup complete. Start the server with:  php vayu run');
        $this->line('');

        return 0;
    }

    protected function step(string $label): void
    {
        $this->comment("  → {$label}");
    }
}
