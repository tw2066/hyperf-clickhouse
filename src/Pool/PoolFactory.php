<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse\Pool;

use Hyperf\Di\Container;
use Psr\Container\ContainerInterface;

class PoolFactory
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var DbPool[]
     */
    protected array $pools = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getPool(string $name): DbPool
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }

        if ($this->container instanceof Container) {
            $pool = $this->container->make(DbPool::class, ['name' => $name]);
        } else {
            $pool = new DbPool($this->container, $name);
        }

        return $this->pools[$name] = $pool;
    }
}
