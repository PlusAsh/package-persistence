<?php declare(strict_types=1);

namespace AshleyHardy\Persistence\Issues;

use Hardy\PeriodApi\Issues\PersistencyIssue;

class MySQLIssue extends PersistencyIssue {
    public function isDuplicate(): bool
    {
        return $this->getCode() == 1062;
    }
}