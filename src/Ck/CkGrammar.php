<?php

namespace Xtwoend\HyperfClickhouse\Ck;

use Tinderbox\ClickhouseBuilder\Exceptions\GrammarException;
use Tinderbox\ClickhouseBuilder\Query\BaseBuilder;
use Tinderbox\ClickhouseBuilder\Query\From;
use Tinderbox\ClickhouseBuilder\Query\Grammar;

class CkGrammar extends Grammar
{
    /**
     * Verifies from.
     *
     * @param From $from
     *
     * @throws GrammarException
     */
    private function verifyFrom(From $from)
    {
        if (is_null($from->getTable())) {
            throw GrammarException::wrongFrom();
        }
    }
    /**
     * Compiles format statement.
     *
     * @param BaseBuilder $builder
     * @param             $from
     *
     * @return string
     */
    public function compileFromComponent(BaseBuilder $builder, From $from): string
    {
        $this->verifyFrom($from);

        $table = $from->getTable();
        $alias = $from->getAlias();
        $final = $from->getFinal();

        $fromSection = '';
        $fromSection .= "FROM {$this->wrap($table)}";

        if (!is_null($alias)) {
            $fromSection .= " AS {$this->wrap($alias)}";
        }

        if ($final) {
            $fromSection .= ' FINAL';
        }

        return $fromSection;
    }

}