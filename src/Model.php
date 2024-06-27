<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Statement;
use Exception;
use Hyperf\Database\Model\Concerns\HasAttributes;
use Hyperf\Stringable\Str;
use function Hyperf\Support\class_basename;

class Model
{
    use HasAttributes;

    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;

    /**
     * The connection name for the model.
     */
    protected string $connection = 'clickhouse';

    /**
     * The table associated with the model.
     */
    protected string $table;

    /**
     * Use this only when you have Buffer table engine for inserts.
     * @see https://clickhouse.tech/docs/ru/engines/table-engines/special/buffer/
     */
    protected string $tableForInserts;

    /**
     * Use this field for OPTIMIZE TABLE OR ALTER TABLE (also DELETE) queries.
     */
    protected string $tableSources;

    protected bool $final = false;

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Get the table name for insert queries.
     */
    public function getTableForInserts(): string
    {
        return $this->tableForInserts ?? $this->getTable();
    }

    /**
     * Use this field for OPTIMIZE TABLE OR ALTER TABLE (also DELETE) queries.
     */
    public function getTableSources(): string
    {
        return $this->tableSources ?? $this->getTable();
    }

    /**
     * Begin querying the model.
     */
    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    public function getFinal(): bool
    {
        return $this->final;
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): Builder
    {
        return (new Builder($this->connection))
            ->from($this->getTableForInserts())
            ->final($this->getFinal());
    }

    /**
     * Create and return an un-saved model instance.
     */
    public static function make(array $attributes = []): static
    {
        $model = new static();
        $model->fill($attributes);
        return $model;
    }

    /**
     * Save a new model and return the instance.
     * @throws Exception
     */
    public static function create(array $attributes = []): static
    {
        $model = static::make($attributes);
        $model->save();
        return $model;
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Save the model to the database.
     */
    public function save(): bool
    {
        if ($this->exists) {
            throw new Exception('Clickhouse does not allow update rows');
        }
        $this->exists = (bool) $this->newQuery()->insert($this->getAttributes());
        return $this->exists;
    }

    /**
     * Necessary stub for HasAttributes trait.
     * @return array
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Necessary stub for HasAttributes trait.
     * @param mixed $key
     */
    public function getRelationValue($key)
    {
        return null;
    }

    /**
     * Optimize table. Using for ReplacingMergeTree, etc.
     * @source https://clickhouse.tech/docs/ru/sql-reference/statements/optimize/
     * @param mixed $final
     * @param null|mixed $partition
     */
    public static function optimize($final = true, $partition = null): Statement
    {
        $sql = 'OPTIMIZE TABLE ' . (new static())->getTableSources();
        if ($partition) {
            $sql .= " PARTITION {$partition}";
        }
        if ($final) {
            $sql .= ' FINAL';
        }
        return DB::query()->getClient()->write($sql);
    }
}
