<?php declare(strict_types=1);

namespace AshleyHardy\Persistence\Query;

use AshleyHardy\Persistence\ConnectionAbstract;
use AshleyHardy\Persistence\Manager;
use RuntimeException;

class Paginator
{
    public const DEFAULT_ROWS_PER_PAGE = 30;

    private int $currentPage = 0;
    private int $rowsPerPage = self::DEFAULT_ROWS_PER_PAGE;
    private ?string $tableName = null;
    private array $columns = [];
    private ?ConnectionAbstract $connection = null;
    private QueryBuilder $queryBuilder;

    public function __construct(int $currentPage = 0, int $rowsPerPage = self::DEFAULT_ROWS_PER_PAGE)
    {
        $this->setCurrentPage($currentPage)->setRowsPerPage($rowsPerPage);
        $this->queryBuilder = new QueryBuilder;
        $this->queryBuilder->order('created_at DESC');
    }

    public function setConnection(ConnectionAbstract $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    private function connection(): ConnectionAbstract
    {
        return (!is_null($this->connection) ? $this->connection : Manager::platform());
    }

    public function setCurrentPage(int $currentPage): self
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    public function setRowsPerPage(int $rowsPerPage): self
    {
        $this->rowsPerPage = $rowsPerPage;
        return $this;
    }   

    public function setTableName(string $tableName): self
    {
        $this->queryBuilder->setTable($tableName);
        return $this;
    }

    public function from(string $tableName): self
    {
        return $this->setTableName($tableName);
    }

    public function getTotalRows(): int
    {
        return (clone $this->queryBuilder)->setConnection($this->connection())->count()->run();
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->getTotalRows() / $this->rowsPerPage);
    }

    public function getPage(): array
    {
        return (clone $this->queryBuilder)->setConnection($this->connection())->select()->limit($this->rowsPerPage, $this->currentPage)->run();
    }

    public function run(): array
    {
        return [
            'currentPage' => $this->currentPage,
            'totalPages' => $this->getTotalPages(),
            'rowsPerPage' => $this->rowsPerPage,
            'totalRows' => $this->getTotalRows(),
            'page' => $this->getPage()
        ];
    }

    public function __call(string $methodName, array $methodArguments): self
    {
        if(method_exists($this->queryBuilder, $methodName)) {
            $this->queryBuilder->$methodName(...$methodArguments);
            return $this;
        } else {
            throw new RuntimeException("Unknown method call in Paginator.");
        }
    }
}