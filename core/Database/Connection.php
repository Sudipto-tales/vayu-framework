<?php

namespace Vayu\Database;

/**
 * A thin wrapper around a PDO connection. Replaces the old `global $pdo`.
 */
class Connection
{
    public function __construct(
        protected \PDO $pdo,
        protected string $driver,
    ) {
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this))->from($table);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->select($sql, $bindings);

        return $rows[0] ?? null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);

        return true;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);

        return true;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function unprepared(string $sql): bool
    {
        return $this->pdo->exec($sql) !== false;
    }

    public function lastInsertId(): string
    {
        return (string) $this->pdo->lastInsertId();
    }

    public function transaction(\Closure $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function run(string $sql, array $bindings): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($bindings));

        return $statement;
    }
}
