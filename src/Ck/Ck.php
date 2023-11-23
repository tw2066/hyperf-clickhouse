<?php

namespace Xtwoend\HyperfClickhouse\Ck;

use Hyperf\Utils\Collection;
use Tinderbox\Clickhouse\Query\Result;

class Ck extends CkBuilder
{
    public static function query(): CkBuilder
    {
        return new static();
    }

    public static function readOne(string $query, array $files = [], array $settings = []): Result
    {
        return static::query()->getClient()->readOne($query, $files, $settings);
    }

    public static function readOneCollect(string $query, array $files = [], array $settings = []): Collection
    {
        return collect(static::readOne($query, $files, $settings)->getRows());
    }

    public static function writeOne(string $query, array $files = [], array $settings = []): bool
    {
        return static::query()->getClient()->writeOne($query, $files, $settings);
    }
}