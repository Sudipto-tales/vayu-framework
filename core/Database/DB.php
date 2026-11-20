<?php

namespace Vayu\Database;

/**
 * Static entry point to the database.
 *
 *   DB::table('users')->get();
 *   DB::select('SELECT * FROM users WHERE id = ?', [1]);
 *   DB::transaction(fn () => ...);
 */
class DB
{
    protected static function manager(): ConnectionManager
    {
        return \app('db');
    }

    public static function connection(?string $name = null): Connection
    {
        return static::manager()->connection($name);
    }

    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        return static::connection($connection)->table($table);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return static::connection()->select($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function insert(string $sql, array $bindings = []): bool
    {
        return static::connection()->insert($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function update(string $sql, array $bindings = []): int
    {
        return static::connection()->affectingStatement($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function delete(string $sql, array $bindings = []): int
    {
        return static::connection()->affectingStatement($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public static function statement(string $sql, array $bindings = []): bool
    {
        return static::connection()->statement($sql, $bindings);
    }

    public static function transaction(\Closure $callback, ?string $connection = null): mixed
    {
        return static::connection($connection)->transaction($callback);
    }
}
