<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Client;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Contract\PoolInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\DbConnection\Traits\DbConnection;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use Psr\Container\ContainerInterface;
use Tang\HyperfClickhouse\Pool\DbPool;

class ClickhouseConnection extends BaseConnection implements ConnectionInterface
{
    use DbConnection;

    /**
     * @var DbPool
     */
    protected PoolInterface $pool;

    /**
     * @var Client
     */
    protected $connection;

    protected array $config;

    protected StdoutLoggerInterface $logger;

    protected bool $transaction = false;

    public function __construct(ContainerInterface $container, DbPool $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = $config;
        $this->logger = $container->get(StdoutLoggerInterface::class);

        $this->reconnect();
    }

    public function __call($name, $arguments)
    {
        return $this->connection->{$name}(...$arguments);
    }

    public function getActiveConnection()
    {
        if ($this->check()) {
            return $this;
        }

        if (! $this->reconnect()) {
            throw new ConnectionException('Connection reconnect failed.');
        }

        return $this;
    }

    public function reconnect(): bool
    {
        $this->close();

        $this->connection = new Client($this->config);
        $this->connection->database($this->config['database']);

        $this->lastUseTime = microtime(true);

        return true;
    }

    public function getClient() : Client
    {
        return $this->connection;
    }

    public function close(): bool
    {
        unset($this->connection);

        return true;
    }
}
