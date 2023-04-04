<?php declare(strict_types=1);

namespace AshleyHardy\Persistence\Query;

use AshleyHardy\Persistence\ConnectionAbstract;
use mysqli;
use mysqli_result;
use RuntimeException;

class QueryBuilder
{
    private const VALID_ACTIONS = ['SELECT', 'INSERT', 'DELETE', 'UPDATE'];
    public const AND = "AND";
    public const OR = "OR";
    public const LIMIT_OFFSET_DEFAULT = 30;

    private ?ConnectionAbstract $connection = null;
    private ?string $tableName = null;
    private string $queryAction = 'unknown';
    private array $columns = [];
    private string $where = "";
    private array $whereParameters = [];
    private ?int $selectLimit = null;
    private ?int $selectLimitOffset = self::LIMIT_OFFSET_DEFAULT;
    private ?string $quickReturn = null;
    private array $order = [];

    private static array $filters = [];
    private bool $hasFiltersApplied = false;

    public static function addFilter(string $action, callable $callable): void
    {
        $action = strtoupper($action);
        if(!in_array($action, self::VALID_ACTIONS)) throw new RuntimeException("Invalid action.");
        self::$filters[$action][] = $callable;
    }

    public static function reset(): void
    {
        self::$filters = [];
    }

    private function applyFilters(): void
    {
        if($this->hasFiltersApplied) return;
        if(!array_key_exists($this->queryAction, self::$filters)) return;

        foreach(self::$filters[$this->queryAction] as $callable) {
            $callable($this);
        }

        $this->hasFiltersApplied = true;
    }

    public function __construct(?ConnectionAbstract $connection = null)
    {
        $this->connection = $connection;
    }

    public function setConnection(ConnectionAbstract $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function setTable(string $table): self
    {
        $this->tableName = $table;
        return $this;
    }

    public function from(string $table): self
    {
        return $this->setTable($table);
    }

    public function into(string $table): self
    {
        return $this->setTable($table);
    }

    public function setAction(string $action): self
    {
        $action = strtoupper($action);
        if(!in_array($action, self::VALID_ACTIONS)) throw new RuntimeException("Invalid query action.");

        $this->queryAction = $action;
        return $this;
    }

    public function select(?array $columns = null): self
    {
        if($columns) $this->setColumns($columns);
        return $this->setAction('SELECT');
    }

    public function insert(array $data): self
    {
        $this->setColumns($data);
        return $this->setAction('INSERT');
    }

    public function update(array $data): self
    {
        $this->setColumns($data);
        return $this->setAction('UPDATE');
    }

    public function delete(): self
    {
        return $this->setAction('DELETE');
    }

    public function column(string $column, mixed $value = null, bool $overwriteIfExists = false): self
    {
        if($this->hasColumn($column) && !$overwriteIfExists) throw new RuntimeException("Duplicate column {$column}.");
        
        $this->columns[$column] = $value;
        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->selectLimit = $limit;
        $this->selectLimitOffset = $offset;
        return $this;
    }

    public function setColumns(array $columns): self
    {
        //An array could come in associatively or not. If it's not associative, we don't have any data.
        //If it's associative, we have fields (array keys) and value (array values).
        
        $fieldNames = array_keys($columns);
        $hasValues = !array_is_list($columns);
        //If not associated, we have no values.
        if(!$hasValues) $fieldNames = $columns;

        foreach($fieldNames as $fieldName) {
            $this->column($fieldName, ($hasValues ? $columns[$fieldName] : null));
        }

        return $this;
    }

    private function hasColumn(string $columnName): bool
    {
        return array_key_exists($columnName, $this->columns);
    }

    private function whereBuilder(string $statement, mixed $value, ?string $mode = null): void
    {
        if($mode !== null && empty($this->where)) throw new RuntimeException("Must begin a where statement with ->where before using and/or.");
        if(!empty($this->where) && $mode === null) $mode = "AND";
        if(!empty($mode)) $mode .= " ";
        
        $this->where .= " {$mode}{$statement}";
        
        if(!is_null($value)) {
            if(is_array($value)) {
                array_push($this->whereParameters, ...$value);
            } else {
                $this->whereParameters[] = $value;
            }
        }
    }

    public function where(string $statement, mixed $value = null): self
    {
        $this->whereBuilder($statement, $value, null);
        return $this;
    }

    public function and(string $statement, mixed $value = null): self
    {
        $this->whereBuilder($statement, $value, 'AND');
        return $this;
    }

    public function or(string $statement, mixed $value = null): self
    {
        $this->whereBuilder($statement, $value, 'OR');
        return $this;
    }

    public function order(string $statement): self
    {
        $this->order[] = $statement;
        return $this;
    }

    public function sql(): string
    {
        $this->applyFilters();
        
        $sql = '';

        switch($this->queryAction) {
            case 'SELECT': {
                $columnString = "*";
                if($this->columns) $columnString = '' . implode(',', array_keys($this->columns)) . '';
                
                $sql = "SELECT {$columnString} FROM `{$this->tableName}`";
                break;
            }
            case 'INSERT': {
                $columnString = "*";
                if($this->columns) $columnString = '' . implode(',', array_keys($this->columns)) . '';
                $valueString = rtrim(str_repeat('?,', count($this->columns)), ',');

                $sql = "INSERT INTO `{$this->tableName}`({$columnString}) VALUES ({$valueString})";
                break;
            }
            case 'UPDATE': {
                $sql = "UPDATE `{$this->tableName}` SET ";
                foreach(array_keys($this->columns) as $column) {
                    $sql .= "`$column` = ?,";
                }
                $sql = rtrim($sql, ',');
                break;
            }
            case 'DELETE': {
                $sql = "DELETE FROM `{$this->tableName}`";
                break;
            }
            default: {
                throw new RuntimeException("QueryBuilder failure: Unknown query action {$this->queryAction}");
            }
        }

        if(!empty($this->where)) {
            $sql .= " WHERE{$this->where}";
        }

        if(!empty($this->order)) {
            $sql .= " ORDER BY " . implode(",", $this->order);
        }

        if($this->queryAction == 'SELECT' && $this->selectLimit) {
            if($this->selectLimitOffset) {
                $sql .= " LIMIT {$this->selectLimitOffset},{$this->selectLimit}";
            } else {
                $sql .= " LIMIT {$this->selectLimit}";
            }
        }

        return $sql;
    }

    public function params(): array
    {
        $this->applyFilters();

        $params = array_values($this->columns);
        if(!empty($this->where)) array_push($params, ...$this->whereParameters);
        foreach($params as $key => $value) {
            if(is_null($value)) unset($params[$key]);
        }
        return array_merge($params);
    }

    public function get(string $fromColumn): mixed
    {
        return $this->columns[$fromColumn] ?? null;
    }

    public function getAll(): array
    {
        return $this->columns;
    }

    public function run(): mixed
    {
        $result = $this->connection->query($this);
        if($this->quickReturn == null) return $result;

        //quickReturn means we will return the that column in the first row - it's used for the helpers such as ->count and ->max
        return $result[0][$this->quickReturn];
    }

    public function __toString(): string
    {
        return $this->sql();
    }

    public function quickReturn(string $fieldName): self
    {
        $this->quickReturn = $fieldName;
        return $this;
    }

    public function count(): self
    {
        return $this->select(['COUNT(1)'])->quickReturn('COUNT(1)');
    }
}