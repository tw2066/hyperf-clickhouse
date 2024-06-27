<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse\Pool;

use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

class PoolFactory
{
    /**
     * @var DbPool[]
     */
    protected array $pools = [];

    public function __construct(protected ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getPool(string $name): DbPool
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }

        $pool = make(DbPool::class, ['name' => $name]);

        return $this->pools[$name] = $pool;
    }
}
