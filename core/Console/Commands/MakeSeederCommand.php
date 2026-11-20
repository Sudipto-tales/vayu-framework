<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class MakeSeederCommand extends Command
{
    protected static string $name = 'make:seeder';

    protected static string $description = 'Create a new seeder file in database/seeders';

    public function handle(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            $this->error('Usage: php vayu make:seeder <Name>');

            return 1;
        }

        $name = ucfirst($name);
        $dir = $this->app->basePath('database/seeders');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir . '/' . $name . '.php';
        if (is_file($path)) {
            $this->error("Seeder already exists: database/seeders/{$name}.php");

            return 1;
        }

        $stub = <<<PHP
        <?php

        use Vayu\Database\Seeder;
        use Vayu\Database\DB;

        return new class extends Seeder {
            public function run(): void
            {
                // DB::table('users')->insert([...]);
            }
        };

        PHP;

        file_put_contents($path, $stub);
        $this->info("Seeder created: database/seeders/{$name}.php");

        return 0;
    }
}
