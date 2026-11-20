<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;
use Vayu\Database\Seeder;

class DbSeedCommand extends Command
{
    protected static string $name = 'db:seed';

    protected static string $description = 'Seed the database (default: DatabaseSeeder)';

    public function handle(array $args): int
    {
        $class = $args[0] ?? 'DatabaseSeeder';
        $file = $this->app->basePath('database/seeders/' . $class . '.php');

        if (!is_file($file)) {
            $this->error("Seeder not found: database/seeders/{$class}.php");

            return 1;
        }

        $seeder = require $file;

        if (!$seeder instanceof Seeder) {
            $this->error("Seeder [{$class}] must return a Seeder instance.");

            return 1;
        }

        try {
            $seeder->run();
        } catch (\Throwable $e) {
            $this->error('Seeding failed: ' . $e->getMessage());

            return 1;
        }

        $this->info("Seeded: {$class}");

        return 0;
    }
}
