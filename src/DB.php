<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Client;

class DB extends Builder
{
    public static function query(): static
    {
        return new static();
    }

    public function getClient(): Client
    {
        return Parent::getClient();
    }

}
