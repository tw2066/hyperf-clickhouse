<?php

declare(strict_types=1);

namespace Tang\HyperfClickhouse;

use ClickHouseDB\Transport\CurlerRequest;
use ClickHouseDB\Transport\CurlerRolling;

class CurlerRollingWithRetries extends CurlerRolling
{
    /**
     * @var int 0 mean only one attempt, 1 mean one attempt + 1 retry while error (2 total attempts)
     */
    protected int $retries = 0;

    public function execOne(CurlerRequest $request, $auto_close = false)
    {
        $attempts = 1 + max(0, $this->retries);
        $httpCode = 0;
        while ($attempts-- && $httpCode !== 200) {
            $httpCode = parent::execOne($request, $auto_close);
        }
        return $httpCode;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function setRetries(int $retries): void
    {
        $this->retries = $retries;
    }
}
