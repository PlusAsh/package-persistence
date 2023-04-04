<?php declare(strict_types=1);

namespace AshleyHardy\Persistence\Connections;

use AshleyHardy\Persistence\ConnectionAbstract;
use mysqli;
use RuntimeException;
use Exception;
use AshleyHardy\Persistence\Query\QueryBuilder;
use mysqli_result;

class MySQL extends ConnectionAbstract
{
    public mysqli $connection;

    public function getRaw(): mysqli
    {
        return $this->connection;
    }

    public function setup(array $parameters = []): void
    {
        $this->connection = new mysqli(
            $parameters['hostname'],
            $parameters['username'],
            $parameters['password'],
            $parameters['database']
        );

        if($this->connection->error) throw new RuntimeException("The database connection failed.");
    }

    public function sql(string $sql): void
    {
        try {
            $this->connection->query($sql);
        } catch(Exception $e) {
            throw new RuntimeException("An exception occurred running an uncontrolled query.");
        }
    }

    public function query(QueryBuilder $query): array|string|bool
    {
        $statement = $this->connection->prepare($query->sql());
        if(!$statement->execute($query->params())) throw new RuntimeException("An error occurred executing a prepared statement: {$statement->error}");
        $result = $statement->get_result();

        if(is_bool($result)) {
            //This was a non-returning SQL statement, so we'll grab the ID if we can.
            return $query->get('id') ?? true;
        }

        return $this->rowsetToArray($result);
    }

    public function getTables(): array
    {
        $queryResult = $this->connection->query("SHOW TABLES");
        $rows = $this->rowsetToArray($queryResult);

        $tables = [];
        foreach($rows as $row) {
            $tables[] = $row[array_keys($row)[0]];
        }
        return $tables;
    }

    private function rowsetToArray(mysqli_result $result): array
    {
        $array = [];
        while($row = $result->fetch_assoc()) {
            $array[] = $row;
        }
        return $array;
    }
}