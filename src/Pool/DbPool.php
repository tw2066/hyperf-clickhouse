<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse\Pool;

use Hyperf\Collection\Arr;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Pool\Pool;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Tang\HyperfClickhouse\ClickhouseConnection;

class DbPool extends Pool
{
    protected string $name;

    protected mixed $config;

    public function __construct(ContainerInterface $container, string $name)
    {
        $this->name = $name;
        $config = $container->get(ConfigInterface::class);
        $key = sprintf('databases.%s', $this->name);
        if (! $config->has($key)) {
            throw new InvalidArgumentException(sprintf('config[%s] is not exist!', $key));
        }
        // Rewrite the `name` of the configuration item to ensure that the model query builder gets the right connection.
        $config->set("{$key}.name", $name);

        $this->config = $config->get($key);
        $options = Arr::get($this->config, 'pool', []);

        parent::__construct($container, $options);
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function createConnection(): ConnectionInterface
    {
        return new ClickhouseConnection($this->container, $this, $this->config);
    }
}
