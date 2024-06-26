<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Statement;
use Hyperf\Database\Migrations\Migration as BaseMigration;

class Migration extends BaseMigration
{

    protected static function write(string $sql): Statement
    {
        $client = DB::query()->getClient();
        return $client->write($sql);
    }
}