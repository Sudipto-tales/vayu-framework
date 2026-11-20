<?php

namespace Vayu\Database\Schema;

/**
 * Collects column/index definitions and compiles them to CREATE / ALTER SQL
 * for sqlite and mysql.
 */
class Blueprint
{
    /** @var list<array<string, mixed>> */
    protected array $columns = [];

    /** @var list<array{type: string, columns: list<string>}> */
    protected array $indexes = [];

    public function __construct(
        public readonly string $table,
        protected string $mode = 'create', // create | alter
    ) {
    }

    /* ---- Column definitions (return $this for chaining modifiers) ---- */

    public function id(string $name = 'id'): static
    {
        return $this->addColumn('bigIncrements', $name);
    }

    public function increments(string $name): static
    {
        return $this->addColumn('increments', $name);
    }

    public function bigIncrements(string $name): static
    {
        return $this->addColumn('bigIncrements', $name);
    }

    public function string(string $name, int $length = 255): static
    {
        return $this->addColumn('string', $name, ['length' => $length]);
    }

    public function text(string $name): static
    {
        return $this->addColumn('text', $name);
    }

    public function integer(string $name): static
    {
        return $this->addColumn('integer', $name);
    }

    public function bigInteger(string $name): static
    {
        return $this->addColumn('bigInteger', $name);
    }

    public function boolean(string $name): static
    {
        return $this->addColumn('boolean', $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): static
    {
        return $this->addColumn('decimal', $name, ['precision' => $precision, 'scale' => $scale]);
    }

    public function float(string $name): static
    {
        return $this->addColumn('float', $name);
    }

    public function date(string $name): static
    {
        return $this->addColumn('date', $name);
    }

    public function dateTime(string $name): static
    {
        return $this->addColumn('dateTime', $name);
    }

    public function timestamp(string $name): static
    {
        return $this->addColumn('timestamp', $name);
    }

    public function json(string $name): static
    {
        return $this->addColumn('json', $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): static
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    /* ---- Column modifiers ---- */

    public function nullable(bool $value = true): static
    {
        $this->columns[array_key_last($this->columns)]['nullable'] = $value;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->columns[array_key_last($this->columns)]['default'] = $value;

        return $this;
    }

    public function unique(): static
    {
        $this->columns[array_key_last($this->columns)]['unique'] = true;

        return $this;
    }

    public function index(): static
    {
        $this->columns[array_key_last($this->columns)]['index'] = true;

        return $this;
    }

    public function unsigned(): static
    {
        $this->columns[array_key_last($this->columns)]['unsigned'] = true;

        return $this;
    }

    /* ---- Table-level indexes ---- */

    /**
     * @param string|list<string> $columns
     */
    public function uniqueIndex(string|array $columns): void
    {
        $this->indexes[] = ['type' => 'unique', 'columns' => (array) $columns];
    }

    /* ---- Compilation ---- */

    /**
     * @return list<string>
     */
    public function toSql(string $driver): array
    {
        $grammar = new Grammar($driver);

        if ($this->mode === 'create') {
            return $grammar->compileCreate($this->table, $this->columns, $this->indexes);
        }

        return $grammar->compileAlter($this->table, $this->columns);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function addColumn(string $type, string $name, array $options = []): static
    {
        $this->columns[] = array_merge([
            'type' => $type,
            'name' => $name,
            'nullable' => false,
            'unique' => false,
            'index' => false,
            'unsigned' => false,
        ], $options);

        return $this;
    }
}
