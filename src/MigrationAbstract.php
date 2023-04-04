<?php declare(strict_types=1);

namespace AshleyHardy\Persistence;

abstract class MigrationAbstract
{
    abstract public function name(): string;    
    abstract public function up(): string;
    abstract public function down(): string;

    final public function identifier(): string
    {
        return strtolower(str_replace(' ', '_', $this->name()) . '/' . md5($this->up()));
    }
}