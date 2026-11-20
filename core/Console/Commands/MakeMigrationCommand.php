<?php

namespace Vayu\Console\Commands;

use Vayu\Console\Command;

class MakeMigrationCommand extends Command
{
    protected static string $name = 'make:migration';

    protected static string $description = 'Create a new migration file';

    public function handle(array $args): int
    {
        $name = $args[0] ?? null;
        if ($name === null) {
            $this->error('Usage: php vayu make:migration <name> [--create=table] [--table=table]');

            return 1;
        }

        $name = $this->toSnake($name);
        $table = null;
        $mode = 'blank';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--create=')) {
                $table = substr($arg, 9);
                $mode = 'create';
            } elseif (str_starts_with($arg, '--table=')) {
                $table = substr($arg, 8);
                $mode = 'table';
            }
        }

        $dir = $this->app->basePath('database/migrations');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = date('Y_m_d_His') . '_' . $name . '.php';
        $path = $dir . '/' . $filename;

        file_put_contents($path, $this->stub($mode, $table));
        $this->info("Migration created: database/migrations/{$filename}");

        return 0;
    }

    protected function stub(string $mode, ?string $table): string
    {
        $table ??= 'table_name';

        $body = match ($mode) {
            'create' => <<<PHP
                    Schema::create('{$table}', function (Blueprint \$table): void {
                            \$table->id();
                            \$table->timestamps();
                        });
            PHP,
            'table' => "        Schema::table('{$table}', function (Blueprint \$table): void {\n            //\n        });",
            default => '        //',
        };

        $down = $mode === 'create'
            ? "        Schema::dropIfExists('{$table}');"
            : '        //';

        return <<<PHP
        <?php

        use Vayu\Database\Migrations\Migration;
        use Vayu\Database\Schema\Blueprint;
        use Vayu\Database\Schema\Schema;

        return new class extends Migration {
            public function up(): void
            {
        {$body}
            }

            public function down(): void
            {
        {$down}
            }
        };

        PHP;
    }

    protected function toSnake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower(str_replace([' ', '-'], '_', $value));
    }
}
