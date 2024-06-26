<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use Hyperf\Context\Context;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Coroutine\Coroutine;
use Psr\Container\ContainerInterface;
use Tang\HyperfClickhouse\Pool\PoolFactory;

use function Hyperf\Coroutine\defer;

class ConnectionResolver
{
    protected string $default = 'clickhouse';

    protected PoolFactory $factory;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->factory = $container->get(PoolFactory::class);
    }

    /**
     * Get a database connection instance.
     */
    public function connection(?string $name = null): ClickhouseConnection
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

        $connection = null;
        $id = $this->getContextKey($name);
        if (Context::has($id)) {
            $connection = Context::get($id);
        }
        
        if (! $connection instanceof ConnectionInterface) {
            $pool = $this->factory->getPool($name);
            $connection = $pool->get();

            try {
                // PDO is initialized as an anonymous function, so there is no IO exception,
                // but if other exceptions are thrown, the connection will not return to the connection pool properly.
                $connection = $connection->getConnection();
                Context::set($id, $connection);
            } finally {
                if (Coroutine::inCoroutine()) {
                    defer(function () use ($connection, $id) {
                        Context::set($id, null);
                        $connection->release();
                    });
                }
            }
        }
        return $connection;
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }

    /**
     * The key to identify the connection object in coroutine context.
     */
    private function getContextKey(string $name): string
    {
        return sprintf('database.connection.%s', $name);
    }
}
