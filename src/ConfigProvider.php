<?php

namespace Xtwoend\HyperfClickhouse;

use Xtwoend\HyperfClickhouse\Pool\PoolFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                PoolFactory::class => PoolFactory::class,
                ConnectionResolver::class => ConnectionResolver::class
            ]
        ];
    }
}