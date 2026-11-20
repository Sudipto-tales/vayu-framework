<?php

namespace Vayu\Database;

/**
 * Fluent query builder.
 *
 *   DB::table('users')->where('active', 1)->orderBy('name')->get();
 */
class QueryBuilder
{
    protected string $from = '';

    /** @var list<string> */
    protected array $columns = ['*'];

    /** @var list<array<string, mixed>> */
    protected array $wheres = [];

    /** @var list<array<string, mixed>> */
    protected array $joins = [];

    /** @var list<string> */
    protected array $groups = [];

    /** @var list<array{column: string, direction: string}> */
    protected array $orders = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    protected bool $distinct = false;

    /** @var array{join: list<mixed>, where: list<mixed>} */
    protected array $bindings = ['join' => [], 'where' => []];

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    public function __construct(protected Connection $connection)
    {
    }

    public function from(string $table): static
    {
        $this->from = $table;

        return $this;
    }

    /**
     * @param class-string<Model> $model
     */
    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * @param string|list<string> $columns
     */
    public function select(string|array $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /* ----------------------------------------------------------------
     | Wheres
     | ---------------------------------------------------------------- */

    public function where(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }

            return $this;
        }

        // where('col', 'value') shorthand
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => $boolean,
        ];
        $this->bindings['where'][] = $value;

        return $this;
    }

    public function orWhere(string|array $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'or');
        }

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'count' => count($values),
            'not' => $not,
            'boolean' => $boolean,
        ];
        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = ['type' => 'null', 'column' => $column, 'not' => $not, 'boolean' => $boolean];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * @param list<mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => $sql, 'boolean' => $boolean];
        foreach ($bindings as $binding) {
            $this->bindings['where'][] = $binding;
        }

        return $this;
    }

    /* ----------------------------------------------------------------
     | Joins / ordering / grouping / paging
     | ---------------------------------------------------------------- */

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
        ];

        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    public function limit(int $value): static
    {
        $this->limit = max(0, $value);

        return $this;
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /* ----------------------------------------------------------------
     | Executors (read)
     | ---------------------------------------------------------------- */

    /**
     * @param string|list<string>|null $columns
     * @return list<array<string, mixed>|Model>
     */
    public function get(string|array|null $columns = null): array
    {
        if ($columns !== null) {
            $this->select($columns);
        }

        $rows = $this->connection->select($this->compileSelect(), $this->flatBindings());

        if ($this->model !== null) {
            return array_map(fn (array $row) => ($this->model)::newFromBuilder($row), $rows);
        }

        return $rows;
    }

    public function first(string|array|null $columns = null): array|Model|null
    {
        $rows = $this->limit(1)->get($columns);

        return $rows[0] ?? null;
    }

    public function find(mixed $id, string $column = 'id'): array|Model|null
    {
        return $this->where($column, '=', $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->first([$column]);

        if ($row instanceof Model) {
            return $row->{$column};
        }

        return $row[$column] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        $this->model = null;

        return array_map(static fn (array $row) => $row[$column] ?? null, $this->get([$column]));
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('count', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('max', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('min', $column);
    }

    public function sum(string $column): mixed
    {
        return $this->aggregate('sum', $column) ?? 0;
    }

    public function avg(string $column): mixed
    {
        return $this->aggregate('avg', $column);
    }

    protected function aggregate(string $function, string $column): mixed
    {
        $previousColumns = $this->columns;
        $previousModel = $this->model;
        $this->model = null;

        $expr = strtoupper($function) . '(' . ($column === '*' ? '*' : $this->wrap($column)) . ') as aggregate';
        $this->columns = [$expr];

        $row = $this->connection->selectOne($this->compileSelect(), $this->flatBindings());

        $this->columns = $previousColumns;
        $this->model = $previousModel;

        return $row['aggregate'] ?? null;
    }

    /* ----------------------------------------------------------------
     | Executors (write)
     | ---------------------------------------------------------------- */

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $values
     */
    public function insert(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        // Normalise to a list of rows.
        if (!array_is_list($values)) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $wrappedColumns = implode(', ', array_map([$this, 'wrap'], $columns));

        $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($values), $placeholderRow));

        $bindings = [];
        foreach ($values as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $sql = 'INSERT INTO ' . $this->wrap($this->from) . ' (' . $wrappedColumns . ') VALUES ' . $placeholders;

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function insertGetId(array $values): int
    {
        $this->insert($values);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        $set = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $set[] = $this->wrap($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrap($this->from) . ' SET ' . implode(', ', $set) . $this->compileWheres();

        return $this->connection->affectingStatement($sql, array_merge($bindings, $this->bindings['where']));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->wrap($this->from) . $this->compileWheres();

        return $this->connection->affectingStatement($sql, $this->bindings['where']);
    }

    /* ----------------------------------------------------------------
     | SQL compilation
     | ---------------------------------------------------------------- */

    public function toSql(): string
    {
        return $this->compileSelect();
    }

    protected function compileSelect(): string
    {
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '')
            . implode(', ', array_map([$this, 'wrapColumn'], $this->columns))
            . ' FROM ' . $this->wrap($this->from);

        $sql .= $this->compileJoins();
        $sql .= $this->compileWheres();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', array_map([$this, 'wrap'], $this->groups));
        }

        if ($this->orders !== []) {
            $parts = array_map(fn ($o) => $this->wrap($o['column']) . ' ' . $o['direction'], $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    protected function compileJoins(): string
    {
        if ($this->joins === []) {
            return '';
        }

        $sql = '';
        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            $sql .= " {$type} JOIN " . $this->wrap($join['table'])
                . ' ON ' . $this->wrap($join['first']) . ' ' . $join['operator'] . ' ' . $this->wrap($join['second']);
        }

        return $sql;
    }

    protected function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : strtoupper($where['boolean']) . ' ';
            $parts[] = $boolean . $this->compileWhere($where);
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $where
     */
    protected function compileWhere(array $where): string
    {
        return match ($where['type']) {
            'basic' => $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?',
            'in' => $this->wrap($where['column']) . ($where['not'] ? ' NOT IN ' : ' IN ')
                . '(' . ($where['count'] > 0 ? implode(', ', array_fill(0, $where['count'], '?')) : 'NULL') . ')',
            'null' => $this->wrap($where['column']) . ($where['not'] ? ' IS NOT NULL' : ' IS NULL'),
            'raw' => $where['sql'],
            default => '1 = 1',
        };
    }

    /**
     * @return list<mixed>
     */
    protected function flatBindings(): array
    {
        return array_merge($this->bindings['join'], $this->bindings['where']);
    }

    protected function wrapColumn(string $column): string
    {
        return $this->wrap($column);
    }

    protected function wrap(string $value): string
    {
        $value = trim($value);

        if ($value === '*' || str_contains($value, '(')) {
            return $value; // expression / aggregate — leave as-is
        }

        if (preg_match('/\s+as\s+/i', $value)) {
            [$column, $alias] = preg_split('/\s+as\s+/i', $value, 2);

            return $this->wrap($column) . ' AS ' . $this->wrapSegment($alias);
        }

        return implode('.', array_map([$this, 'wrapSegment'], explode('.', $value)));
    }

    protected function wrapSegment(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '*') {
            return '*';
        }

        if ($this->connection->driver() === 'mysql') {
            return '`' . str_replace('`', '``', $segment) . '`';
        }

        return '"' . str_replace('"', '""', $segment) . '"';
    }
}
