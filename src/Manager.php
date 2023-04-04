<?php declare(strict_types=1);

namespace AshleyHardy\Persistence;

use RuntimeException;

class Manager
{
    public const PLATFORM = "platform";

    private static array $connections = [];

    public static function connection(string $name): ConnectionAbstract
    {
        if(!isset(self::$connections[$name])) throw new RuntimeException("The requested connection does not exist.");
        return self::$connections[$name];
    }

    public static function add(string $name, ConnectionAbstract $connection): void
    {
        self::$connections[$name] = $connection;
    }

    public static function platform(): ConnectionAbstract
    {
        return self::$connections[self::PLATFORM];
    }
}