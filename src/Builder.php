<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Hyperf\Collection\Collection;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Paginator\Paginator;
use RuntimeException;
use Tinderbox\ClickhouseBuilder\Query\Builder as BaseBuilder;
use Tinderbox\ClickhouseBuilder\Query\Grammar;

/** @phpstan-consistent-constructor */
class Builder extends BaseBuilder
{
    /**
     * @var Client
     */
    protected $client;

    protected string $connection;

    public function __construct($connection = 'clickhouse')
    {
        $this->connection = $connection;
        $this->grammar = new Grammar();
    }

    public function connection(string $connection): void
    {
        $this->connection = $connection;
    }

    protected function getClient(): Client
    {
        $this->client ??= Clickhouse::connection($this->connection)->getClient();
        return $this->client;
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): void
    {
        $offset = 0;
        do {
            $rows = $this->limit($count, $offset)->get()->rows();
            $callback($rows);
            $offset += $count;
        } while ($rows);
    }

    public function getCollect(): Collection
    {
        $result = $this->get();
        $data = [];
        if (is_array($result)) {
            foreach ($result as $value) {
                $data[] = $value->rows();
            }
        } else {
            $data = $result->rows();
        }
        return new Collection($data);
    }

    public function first(): ?Collection
    {
        $result = $this->limit(1)->getCollect();
        return $result->isNotEmpty() ? new Collection($result->first()) : null;
    }

    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): LengthAwarePaginatorInterface
    {
        $page = $page ?: Paginator::resolveCurrentPage();
        $total = $this->count();
        $results = $total > 0 ? $this->limit($perPage, $perPage * ($page - 1))->getCollect() : new Collection();
        return $this->paginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    public function simplePaginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): PaginatorInterface
    {
        $page = $page ?: Paginator::resolveCurrentPage();
        $results = $this->limit($perPage + 1, $perPage * ($page - 1))->getCollect();

        return $this->simplePaginator(
            $results,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Create a new simple paginator instance.
     */
    protected function simplePaginator(Collection $items, int $perPage, int $currentPage, array $options): PaginatorInterface
    {
        $container = ApplicationContext::getContainer();
        if (! method_exists($container, 'make')) {
            throw new RuntimeException('The DI container does not support make() method.');
        }

        return $container->make(PaginatorInterface::class, compact('items', 'perPage', 'currentPage', 'options'));
    }

    protected function paginator(Collection $items, int $total, int $perPage, int $currentPage, array $options): LengthAwarePaginatorInterface
    {
        $container = ApplicationContext::getContainer();
        if (! method_exists($container, 'make')) {
            throw new RuntimeException('The DI container does not support make() method.');
        }

        return $container->make(LengthAwarePaginatorInterface::class, compact('items', 'total', 'perPage', 'currentPage', 'options'));
    }
}
