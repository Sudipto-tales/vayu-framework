<?php

namespace Vayu\Database\Schema;

/**
 * Compiles Blueprint definitions into driver-specific DDL.
 */
class Grammar
{
    public function __construct(protected string $driver)
    {
    }

    /**
     * @param list<array<string, mixed>>                   $columns
     * @param list<array{type: string, columns: list<string>}> $indexes
     * @return list<string>
     */
    public function compileCreate(string $table, array $columns, array $indexes): array
    {
        $definitions = [];
        $extraStatements = [];

        foreach ($columns as $column) {
            $definitions[] = $this->compileColumn($column);

            if ($column['unique']) {
                $extraStatements[] = $this->compileUniqueIndex($table, [$column['name']]);
            } elseif ($column['index']) {
                $extraStatements[] = $this->compileIndex($table, [$column['name']]);
            }
        }

        foreach ($indexes as $index) {
            $extraStatements[] = $index['type'] === 'unique'
                ? $this->compileUniqueIndex($table, $index['columns'])
                : $this->compileIndex($table, $index['columns']);
        }

        $suffix = $this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

        $create = 'CREATE TABLE IF NOT EXISTS ' . $this->wrap($table)
            . " (\n    " . implode(",\n    ", $definitions) . "\n)" . $suffix;

        return array_merge([$create], $extraStatements);
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return list<string>
     */
    public function compileAlter(string $table, array $columns): array
    {
        $statements = [];
        foreach ($columns as $column) {
            $statements[] = 'ALTER TABLE ' . $this->wrap($table) . ' ADD COLUMN ' . $this->compileColumn($column);

            if ($column['unique']) {
                $statements[] = $this->compileUniqueIndex($table, [$column['name']]);
            } elseif ($column['index']) {
                $statements[] = $this->compileIndex($table, [$column['name']]);
            }
        }

        return $statements;
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrap($table);
    }

    /**
     * @param array<string, mixed> $column
     */
    protected function compileColumn(array $column): string
    {
        // Auto-increment primary keys are special-cased per driver.
        if (in_array($column['type'], ['increments', 'bigIncrements'], true)) {
            return $this->compileAutoIncrement($column['name']);
        }

        $sql = $this->wrap($column['name']) . ' ' . $this->mapType($column);

        if (($column['unsigned'] ?? false) && $this->driver === 'mysql') {
            $sql .= ' UNSIGNED';
        }

        $sql .= ($column['nullable'] ?? false) ? ' NULL' : ' NOT NULL';

        if (array_key_exists('default', $column)) {
            $sql .= ' DEFAULT ' . $this->formatDefault($column['default']);
        }

        return $sql;
    }

    protected function compileAutoIncrement(string $name): string
    {
        if ($this->driver === 'mysql') {
            return $this->wrap($name) . ' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }

        // sqlite
        return $this->wrap($name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    /**
     * @param array<string, mixed> $column
     */
    protected function mapType(array $column): string
    {
        $mysql = $this->driver === 'mysql';

        return match ($column['type']) {
            'string' => $mysql ? 'VARCHAR(' . ($column['length'] ?? 255) . ')' : 'VARCHAR(' . ($column['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => $mysql ? 'INT' : 'INTEGER',
            'bigInteger' => $mysql ? 'BIGINT' : 'INTEGER',
            'boolean' => $mysql ? 'TINYINT(1)' : 'INTEGER',
            'decimal' => 'DECIMAL(' . ($column['precision'] ?? 8) . ',' . ($column['scale'] ?? 2) . ')',
            'float' => $mysql ? 'DOUBLE' : 'REAL',
            'date' => 'DATE',
            'dateTime' => $mysql ? 'DATETIME' : 'DATETIME',
            'timestamp' => $mysql ? 'TIMESTAMP' : 'DATETIME',
            'json' => $mysql ? 'JSON' : 'TEXT',
            default => 'TEXT',
        };
    }

    protected function formatDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_string($value) && str_starts_with($value, 'CURRENT_TIMESTAMP')) {
            return $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * @param list<string> $columns
     */
    protected function compileIndex(string $table, array $columns): string
    {
        $name = $this->indexName($table, $columns, 'index');

        return 'CREATE INDEX IF NOT EXISTS ' . $this->wrap($name)
            . ' ON ' . $this->wrap($table) . ' (' . $this->columnize($columns) . ')';
    }

    /**
     * @param list<string> $columns
     */
    protected function compileUniqueIndex(string $table, array $columns): string
    {
        $name = $this->indexName($table, $columns, 'unique');

        return 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $this->wrap($name)
            . ' ON ' . $this->wrap($table) . ' (' . $this->columnize($columns) . ')';
    }

    /**
     * @param list<string> $columns
     */
    protected function indexName(string $table, array $columns, string $type): string
    {
        return $table . '_' . implode('_', $columns) . '_' . $type;
    }

    /**
     * @param list<string> $columns
     */
    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    protected function wrap(string $value): string
    {
        if ($this->driver === 'mysql') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
