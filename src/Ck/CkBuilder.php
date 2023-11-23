<?php

declare(strict_types=1);

namespace Xtwoend\HyperfClickhouse\Ck;

use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Paginator\Paginator;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Hyperf\Utils\Str;
use Tinderbox\ClickhouseBuilder\Exceptions\GrammarException;
use Tinderbox\ClickhouseBuilder\Query\BaseBuilder;
use Tinderbox\ClickhouseBuilder\Query\JoinClause;
use Xtwoend\HyperfClickhouse\Builder;
use Xtwoend\HyperfClickhouse\Clickhouse;

class CkBuilder extends Builder
{

    public function __construct()
    {
        $this->client = Clickhouse::connection('clickhouse')->getClient();
        $this->grammar = new CkGrammar();
    }


    /**
     *
     * @param int $perPage
     *
     * @return array
     */
    public function page(int $perPage = 15): array
    {
        $pageData = $this->paginate($perPage);

        return [
            'total' => $pageData->total(),
            'list'  => $pageData->items(),
        ];
    }


    /**
     * 模糊查询
     *
     * author: ZhengYi
     * date: 2023/8/18
     *
     * @param string $column
     * @param string $keyWords %keyWords% or keyWords%
     *
     * @return $this
     */
    public function whereLike(string $column, string $keyWords)
    {
        $this->whereRaw("$column like '$keyWords'");

        return $this;
    }


    public function whereNull(string $column)
    {
        $this->whereRaw("{$column} is null");

        return $this;
    }

    
    public function whereNotNull(string $column)
    {
        $this->whereRaw("{$column} is not null");

        return $this;
    }

    public function paginateParallel(int $perPage = 15, ?int $page = null, string $pageName = 'page',bool $firstCount = false): LengthAwarePaginatorInterface
    {
        $page    = $page ?: Paginator::resolveCurrentPage();
        [$total,$results] = parallel([
            function () use($page,$firstCount){
                if($firstCount && $page > 1){
                    return -1;
                }
                return $this->count();
            },
            function () use($perPage,$page){
                return $this->limit($perPage, $perPage * ($page - 1))->getCollect();
            }
        ],2);

        return $this->paginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }


    public function paginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): LengthAwarePaginatorInterface
    {
        $page    = $page ?: Paginator::resolveCurrentPage();
        $total   = $this->count();
        $results = $total ? $this->limit($perPage, $perPage * ($page - 1))->getCollect() : collect([]);

        return $this->paginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    public function simplePaginate(int $perPage = 15, ?int $page = null, string $pageName = 'page'): PaginatorInterface
    {
        $page    = $page ?: Paginator::resolveCurrentPage();
        $results = $this->limit($perPage + 1, $perPage * ($page - 1))->getCollect();

        return $this->simplePaginator(
            $results,
            $perPage,
            $page,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    public function getCollect(array $settings = []): Collection
    {
        $result = is_array($arr = $this->get($settings)) ? $arr : $arr->getRows();
        $array  = [];
        foreach ($result as $numKey => $row) {
            foreach ($row as $key => $value) {
                $array[$numKey][$this->keyTransform($key)] = $value;
            }
        }

        return collect($array);
    }

    public function first(): ?Collection
    {
        $result = $this->limit(1)->getCollect();

        return $result->isNotEmpty() ? collect($result->first()) : null;
    }

    /**
     * Performs compiled sql for count rows only. May be used for pagination
     * Works only without async queries.
     *
     * @return int
     */
    public function count()
    {
        if (!empty($this->groups)) {
            $subThis = clone $this;
            $subThis->orders = [];
            return $this->newQuery()->from($subThis)->count();
        }
        $builder = $this->getCountQuery();
        $result  = $builder->get();

        return intval($result[0]['count'] ?? 0);
    }

    /**
     * Performs ALTER TABLE `table` DELETE query.
     *
     * @return bool
     * @throws \Tinderbox\ClickhouseBuilder\Exceptions\GrammarException
     */
    public function update(array $values)
    {
        return $this->client->writeOne(
            $this->compileUpdate($this, $values)
        );
    }

    /**
     * Alias for from method.
     *
     * @param $table
     *
     * @return static
     */
    public function table($table, string $alias = null, bool $isFinal = null)
    {
        return parent::table($table, $alias, $isFinal);
    }
    

    /**
     * Apply the callback's query changes if the given "value" is true.
     *
     * @param callable $callback
     * @param callable $default
     * @param mixed    $value
     *
     * @return $this|mixed
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }
        if ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    protected function paginator(Collection $items, int $total, int $perPage, int $currentPage, array $options): LengthAwarePaginatorInterface
    {
        $container = ApplicationContext::getContainer();
        if (!method_exists($container, 'make')) {
            throw new \RuntimeException('The DI container does not support make() method.');
        }

        return $container->make(LengthAwarePaginatorInterface::class, compact('items', 'total', 'perPage', 'currentPage', 'options'));
    }

    protected function keyTransform($key)
    {
        return Str::camel($key);
    }

    /**
     * Create a new simple paginator instance.
     */
    protected function simplePaginator(Collection $items, int $perPage, int $currentPage, array $options): PaginatorInterface
    {
        $container = ApplicationContext::getContainer();
        if (!method_exists($container, 'make')) {
            throw new \RuntimeException('The DI container does not support make() method.');
        }

        return $container->make(PaginatorInterface::class, compact('items', 'perPage', 'currentPage', 'options'));
    }

    protected function compileUpdate(BaseBuilder $query, array $values): string
    {
        if (is_null($query->getFrom()->getTable())) {
            throw GrammarException::wrongFrom();
        }
        $sql = "ALTER TABLE {$this->grammar->wrap($query->getFrom()->getTable())}";

        $sql .= ' UPDATE ';

        $columns = collect($values)->map(
            function ($value, $key) {
                return '`' . $key . '`' . ' = ' . $value;
            }
        )->implode(', ');

        $sql .= $columns;

        if (!is_null($query->getWheres()) && !empty($query->getWheres())) {
            $sql .= " {$this->grammar->compileWheresComponent($query, $query->getWheres())}";
        } else {
            throw new GrammarException('Missed where section for update statement.');
        }

        return $sql;
    }
}
