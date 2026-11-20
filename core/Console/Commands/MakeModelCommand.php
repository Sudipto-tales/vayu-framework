<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class MakeModelCommand extends Command
{
    protected static string $name = 'make:model';

    protected static string $description = 'Create a new model class in app/Models';

    public function handle(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            $this->error('Usage: php vayu make:model <Name>');

            return 1;
        }

        $name = ucfirst($name);
        $dir = $this->app->basePath('app/Models');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $dir . '/' . $name . '.php';
        if (is_file($path)) {
            $this->error("Model already exists: app/Models/{$name}.php");

            return 1;
        }

        $stub = <<<PHP
        <?php

        namespace App\Models;

        use Vayu\Database\Model;

        class {$name} extends Model
        {
            /**
             * Mass-assignable attributes.
             *
             * @var list<string>
             */
            protected array \$fillable = [];
        }

        PHP;

        file_put_contents($path, $stub);
        $this->info("Model created: app/Models/{$name}.php");

        return 0;
    }
}
