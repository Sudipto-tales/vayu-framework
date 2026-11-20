<?php

namespace Vayu\Database;

/**
 * Lightweight Active-Record model.
 *
 *   class User extends Model {}
 *   User::find(1);
 *   User::where('active', 1)->get();
 *   $user = User::create(['name' => 'Ada']);
 *   $user->name = 'Ada L.';
 *   $user->save();
 *
 * @method static QueryBuilder where(string|array $column, mixed $operator = null, mixed $value = null)
 * @method static QueryBuilder orderBy(string $column, string $direction = 'asc')
 * @method static QueryBuilder whereIn(string $column, array $values)
 * @method static QueryBuilder limit(int $value)
 */
abstract class Model implements \JsonSerializable
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    protected ?string $connection = null;

    protected bool $incrementing = true;

    public bool $timestamps = true;

    /** @var list<string> */
    protected array $fillable = [];

    /** @var list<string> */
    protected array $guarded = ['id'];

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    public bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /* ----------------------------------------------------------------
     | Attributes & mass assignment
     | ---------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if ($this->fillable !== []) {
            return in_array($key, $this->fillable, true);
        }

        return !in_array($key, $this->guarded, true);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /* ----------------------------------------------------------------
     | Table / connection
     | ---------------------------------------------------------------- */

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        return static::pluralize(static::snake(static::classBasename()));
    }

    public function getConnection(): Connection
    {
        /** @var ConnectionManager $manager */
        $manager = \app('db');

        return $manager->connection($this->connection);
    }

    /* ----------------------------------------------------------------
     | Query entry points
     | ---------------------------------------------------------------- */

    public function newQuery(): QueryBuilder
    {
        return $this->getConnection()
            ->table($this->getTable())
            ->setModel(static::class);
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    public static function find(mixed $id): ?static
    {
        $instance = new static();

        return $instance->newQuery()->find($id, $instance->getKeyName());
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new \RuntimeException(static::class . " with id [{$id}] not found.");
        }

        return $model;
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Forward static calls (where, orderBy, ...) to a fresh query builder.
     *
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::query()->{$method}(...$arguments);
    }

    /* ----------------------------------------------------------------
     | Persistence
     | ---------------------------------------------------------------- */

    public function save(): bool
    {
        $query = $this->newQuery();

        if ($this->exists) {
            $dirty = $this->getDirty();
            if ($dirty === []) {
                return true;
            }

            if ($this->timestamps) {
                $dirty['updated_at'] = $this->freshTimestamp();
                $this->attributes['updated_at'] = $dirty['updated_at'];
            }

            $query->where($this->getKeyName(), $this->getKey())->update($dirty);
            $this->syncOriginal();

            return true;
        }

        if ($this->timestamps) {
            $now = $this->freshTimestamp();
            $this->attributes['created_at'] ??= $now;
            $this->attributes['updated_at'] ??= $now;
        }

        if ($this->incrementing) {
            $id = $query->insertGetId($this->attributes);
            $this->attributes[$this->getKeyName()] = $id;
        } else {
            $query->insert($this->attributes);
        }

        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    public function refresh(): static
    {
        $fresh = static::find($this->getKey());
        if ($fresh !== null) {
            $this->attributes = $fresh->attributes;
            $this->syncOriginal();
        }

        return $this;
    }

    /* ----------------------------------------------------------------
     | Dirty tracking / hydration
     | ---------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(): bool
    {
        return $this->getDirty() !== [];
    }

    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Build a model instance from a raw database row (bypasses mass assignment).
     *
     * @param array<string, mixed> $attributes
     */
    public static function newFromBuilder(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->syncOriginal();
        $model->exists = true;

        return $model;
    }

    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /* ----------------------------------------------------------------
     | Serialization
     | ---------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->attributes, $flags);
    }

    public function jsonSerialize(): mixed
    {
        return $this->attributes;
    }

    /* ----------------------------------------------------------------
     | Naming helpers
     | ---------------------------------------------------------------- */

    protected static function classBasename(): string
    {
        $class = static::class;
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    protected static function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }

    protected static function pluralize(string $value): string
    {
        if (str_ends_with($value, 'y') && !preg_match('/[aeiou]y$/', $value)) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $value)) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
