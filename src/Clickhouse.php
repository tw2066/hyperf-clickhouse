<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use Hyperf\Context\ApplicationContext;

class Clickhouse
{
    public static function connection($pool = 'default')
    {
        $resolver = ApplicationContext::getContainer()->get(ConnectionResolver::class);
        return $resolver->connection($pool);
    }
}
