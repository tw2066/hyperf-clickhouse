<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use Tang\HyperfClickhouse\Pool\PoolFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                PoolFactory::class => PoolFactory::class,
                ConnectionResolver::class => ConnectionResolver::class,
            ],
        ];
    }
}
