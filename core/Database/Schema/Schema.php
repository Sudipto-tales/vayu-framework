<?php

namespace Vayu\Database\Schema;

use Vayu\Database\Connection;
use Vayu\Database\DB;

/**
 * Static schema builder.
 *
 *   Schema::create('users', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('email')->unique();
 *       $table->timestamps();
 *   });
 */
class Schema
{
    protected static ?string $connection = null;

    public static function connection(?string $name): string
    {
        $previous = static::$connection;
        static::$connection = $name;

        return (string) $previous;
    }

    protected static function conn(): Connection
    {
        return DB::connection(static::$connection);
    }

    public static function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);

        static::build($blueprint);
    }

    public static function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'alter');
        $callback($blueprint);

        static::build($blueprint);
    }

    public static function dropIfExists(string $table): void
    {
        $grammar = new Grammar(static::conn()->driver());
        static::conn()->statement($grammar->compileDropIfExists($table));
    }

    public static function hasTable(string $table): bool
    {
        $connection = static::conn();

        if ($connection->driver() === 'mysql') {
            $row = $connection->selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
        } else {
            $row = $connection->selectOne(
                "SELECT COUNT(*) AS c FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
        }

        return (int) ($row['c'] ?? 0) > 0;
    }

    /**
     * @return list<string>
     */
    public static function tableNames(): array
    {
        $connection = static::conn();

        if ($connection->driver() === 'mysql') {
            $rows = $connection->select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()');
        } else {
            $rows = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        }

        return array_map(static fn (array $row) => (string) $row['name'], $rows);
    }

    public static function dropAllTables(): void
    {
        $connection = static::conn();
        $grammar = new Grammar($connection->driver());

        if ($connection->driver() === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
            foreach (static::tableNames() as $table) {
                $connection->statement($grammar->compileDropIfExists($table));
            }
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');

            return;
        }

        $connection->statement('PRAGMA foreign_keys = OFF');
        foreach (static::tableNames() as $table) {
            $connection->statement($grammar->compileDropIfExists($table));
        }
        $connection->statement('PRAGMA foreign_keys = ON');
    }

    protected static function build(Blueprint $blueprint): void
    {
        $connection = static::conn();
        foreach ($blueprint->toSql($connection->driver()) as $statement) {
            $connection->statement($statement);
        }
    }
}
