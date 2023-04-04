<?php declare(strict_types=1);

namespace AshleyHardy\Persistence;

use AshleyHardy\Persistence\Query\QueryBuilder;
use RuntimeException;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

abstract class ConnectionAbstract
{
    public function __construct(array $parameters = [])
    {
        $this->setup($parameters);
    }

    abstract public function setup(array $parameters = []): void;
    
    abstract public function getRaw(): mixed;

    abstract public function query(QueryBuilder $qb): mixed;

    abstract public function getTables(): array;

    abstract public function sql(string $sql): void;

    public function tableExists(string $tableName): bool
    {
        return in_array($tableName, $this->getTables());
    }
}