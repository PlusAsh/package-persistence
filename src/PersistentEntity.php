<?php declare(strict_types=1);

namespace AshleyHardy\Persistence;

use AshleyHardy\Persistence\Query\QueryBuilder;
use AshleyHardy\Utilities\Utils;
use ReflectionClass;
use RuntimeException;

abstract class PersistentEntity
{
    public ?string $id;
    public ?string $modifiedAt;
    public ?string $createdAt;
    private ?ConnectionAbstract $connection = null;

    abstract static public function table(): string;
    abstract public function persist(): array;

    private function insert(): QueryBuilder
    {
        return (new QueryBuilder)->insert($this->toArray())->into(static::table());
    }

    private function update(): QueryBuilder
    {
        return (new QueryBuilder)->update($this->toArray())->into(static::table())->where('id = ?', $this->id);
    }

    private function delete(): QueryBuilder
    {
        return (new QueryBuilder)->delete()->from(static::table())->where('id = ?', $this->id);
    }

    protected function connection(): ConnectionAbstract
    {
        return $this->connection ?? Manager::platform();
    }
    
    public function save(?ConnectionAbstract $connection = null): self
    {
        if($connection === null) $connection = $this->connection();
        $query = null;

        if(!Utils::isPropertyInitialised('id', $this) || $this->id === null) {
            $query = $this->insert();
        } else {
            $query = $this->update();
        }

        $connection->query($query);

        //Our special prop's will be updated (id, createdAt, modifiedAt)...
        $this->id = $query->get('id');
        $this->createdAt = $query->get('created_at');
        $this->modifiedAt = $query->get('modified_at');

        return $this;
    }

    public static function fetch(string $id, ?ConnectionAbstract $connection = null): ?static
    {
        $instance = new static();
        if($connection === null) $connection = $instance->connection();

        $results = (new QueryBuilder($connection))->select()->from(static::table())->where('id = ?', $id)->run();
        if(!$results) return null;
        return self::fromArray($results[0]);
    }

    public static function fetchAll(): array
    {
        $query = (new QueryBuilder)->select()->from(static::table());
        $rows = (new static)->connection()->query($query);
        return self::rowsToEntities($rows);
    }

    public static function fetchFrom(string $column, mixed $value): array
    {
        $query = (new QueryBuilder)->select()->from(static::table())->where("{$column} = ?", $value);
        $rows = (new static())->connection()->query($query);
        return self::rowsToEntities($rows);
    }

    public static function fetchOneFrom(string $column, mixed $value): ?static
    {
        return self::fetchFrom($column, $value)[0] ?? null;
    }

    public static function rowsToEntities(array $rows): array
    {
        $objects = [];
        foreach($rows as $row) {
            $objects[] = self::fromArray($row);
        }
        return $objects;
    }

    public function toArray(): array
    {
        $array = [];

        $persistentProperties = array_merge(['id'], $this->persist());
        foreach($persistentProperties as $prop) {
            $array[Utils::camelToSnake($prop)] = (Utils::isPropertyInitialised($prop, $this) ? $this->$prop : null);
        }

        return $array;
    }

    public static function fromArray(array $data): PersistentEntity
    {
        $instance = new static;
        foreach($data as $field => $value) {
            $property = Utils::getPropertyFromEntity($instance, Utils::snakeToCamel($field));
            if($property) {
                $property->setValue($instance, $value);
            }
        }
        return $instance;
    }

    private function relation(string $entityClass, string $localColumn, string $foreignColumn): ?array
    {
        return (new QueryBuilder($this->connection()))->select()->from($entityClass::table())->where("{$foreignColumn} = ?", $this->$localColumn)->run();
    }

    public function hasOne(string $entityClass, string $localColumn, string $foreignColumn): PersistentEntity
    {
        $results = $this->relation($entityClass, $localColumn, $foreignColumn);
        if(count($results) != 1) throw new RuntimeException("hasOne relationship returned more than one related record.");
        return $entityClass::fromArray($results[0]);
    }

    public function hasMany(string $entityClass, string $localColumn, string $foreignColumn): array
    {
        $results = $this->relation($entityClass, $localColumn, $foreignColumn);
        return is_array($results) ? $results : [];
    }
}