<?php

namespace Vayu\Database\Migrations;

use Vayu\Database\Connection;
use Vayu\Database\DB;
use Vayu\Database\Schema\Schema;

/**
 * Discovers, runs and rolls back migrations, tracking them in a migrations
 * table.
 */
class Migrator
{
    protected string $table = 'migrations';

    /** @var list<string> */
    public array $log = [];

    /** @var array<string, Migration>|null name => resolved migration */
    protected ?array $resolved = null;

    public function __construct(protected string $path)
    {
    }

    protected function connection(): Connection
    {
        return DB::connection();
    }

    public function ensureTable(): void
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function ($table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }
    }

    /**
     * Run all pending migrations.
     *
     * @return int number of migrations run
     */
    public function run(): int
    {
        $this->ensureTable();

        $ran = $this->ran();
        $batch = $this->nextBatch();
        $count = 0;

        foreach ($this->migrations() as $name => $migration) {
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration->up();
            $this->connection()->table($this->table)->insert(['migration' => $name, 'batch' => $batch]);
            $this->log[] = "migrated: {$name}";
            $count++;
        }

        return $count;
    }

    /**
     * Roll back the last batch (or $steps batches).
     *
     * @return int number of migrations rolled back
     */
    public function rollback(int $steps = 1): int
    {
        $this->ensureTable();

        $count = 0;
        for ($i = 0; $i < $steps; $i++) {
            $batch = $this->lastBatch();
            if ($batch === 0) {
                break;
            }

            $rows = $this->connection()->table($this->table)
                ->where('batch', $batch)
                ->orderBy('id', 'desc')
                ->get();

            $migrations = $this->migrations();
            foreach ($rows as $row) {
                $name = $row['migration'];
                if (isset($migrations[$name])) {
                    $migrations[$name]->down();
                }
                $this->connection()->table($this->table)->where('migration', $name)->delete();
                $this->log[] = "rolled back: {$name}";
                $count++;
            }
        }

        return $count;
    }

    /**
     * Drop everything and migrate from scratch.
     */
    public function fresh(): int
    {
        Schema::dropAllTables();
        $this->log[] = 'dropped all tables';

        return $this->run();
    }

    /**
     * @return array<string, array{ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $this->ensureTable();

        $ranRows = $this->connection()->table($this->table)->get();
        $batches = [];
        foreach ($ranRows as $row) {
            $batches[$row['migration']] = (int) $row['batch'];
        }

        $status = [];
        foreach (array_keys($this->migrations()) as $name) {
            $status[$name] = [
                'ran' => isset($batches[$name]),
                'batch' => $batches[$name] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Resolve every migration file exactly once (each file is required a single
     * time — a second require of the same path returns true, not the object).
     * Files that do not return a Migration instance (e.g. legacy classes) are
     * ignored.
     *
     * @return array<string, Migration> name => migration, sorted by name
     */
    protected function migrations(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $files = glob($this->path . '/*.php') ?: [];
        sort($files);

        $this->resolved = [];
        foreach ($files as $file) {
            $migration = require $file;
            if ($migration instanceof Migration) {
                $this->resolved[basename($file, '.php')] = $migration;
            }
        }

        return $this->resolved;
    }

    /**
     * @return list<string>
     */
    protected function ran(): array
    {
        return $this->connection()->table($this->table)->pluck('migration');
    }

    protected function nextBatch(): int
    {
        return $this->lastBatch() + 1;
    }

    protected function lastBatch(): int
    {
        return (int) $this->connection()->table($this->table)->max('batch');
    }
}
