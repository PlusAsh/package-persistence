<?php declare(strict_types=1);

namespace AshleyHardy\Persistence;

use Hardy\PeriodApi\Issues\MigrationIssue;
use AshleyHardy\Persistence\Query\QueryBuilder;
use AshleyHardy\Utilities\Utils;

final class Migrations
{
    private string $pathToMigrations;
    private ConnectionAbstract $connection;
    private int $batch;

    public function __construct(string $pathToMigrations, ConnectionAbstract $connection)
    {
        $this->connection = $connection;

        if(!$this->connection->tableExists('migrations')) {
            $this->setupMigrationsTable();
        }

        $this->pathToMigrations = realpath($pathToMigrations);
        $this->batch = $this->getNextBatchNumber();
    }

    private function getMigrationsFromFiles(bool $pending = true, ?int $batch = null): array
    {
        $migrationClasses = [];
        $migrationFiles = scandir($this->pathToMigrations);
        $existingMigrations = $this->getMigrations();

        foreach($migrationFiles as $migrationFile) {
            if(in_array($migrationFile, ['.', '..'])) continue;

            $migrationPath = "{$this->pathToMigrations}/$migrationFile";

            /** @var MigrationAbstract */
            $class = new (require($migrationPath));
            $identifier = $class->identifier();

            if($pending) {
                if(isset($existingMigrations[$identifier]) && !$existingMigrations[$identifier]['rolled_back']) continue;
            } else {
                if(!isset($existingMigrations[$identifier]) || $existingMigrations[$identifier]['rolled_back'] || $existingMigrations[$identifier]['batch'] != $batch) continue;
            }

            $migrationClasses[] = $class;
        }

        return $migrationClasses;
    }

    public function execute(bool $up = true): void
    {
        $migrations = $up ? $this->getMigrationsFromFiles() : $this->getMigrationsFromFiles(false, $this->getCurrentBatchNumber());
        $method = ($up ? 'up' : 'down');

        foreach($migrations as $migration) {
            $this->$method($migration);
        }
    }

    private function up(MigrationAbstract $migration): void
    {
        $this->connection->sql($migration->up());

        $query = new QueryBuilder();
        $query->insert([
            'name' => $migration->name(),
            'identifier' => $migration->identifier(),
            'batch' => $this->batch
        ])->into('migrations');

        $this->connection->query($query);
    }

    private function down(MigrationAbstract $migration): void
    {
        $existingMigration = $this->getMigrations()[$migration->identifier()] ?? null;
        if($existingMigration == null) throw MigrationIssue::migrationRollbackFailed($migration);

        $this->connection->sql($migration->down());

        $query = new QueryBuilder();
        $query->update([
            'rolled_back' => 1,
            'rolled_back_at' => Utils::datetime()
        ])->into('migrations')->where('id = ?', $existingMigration['id']);

        $this->connection->query($query);
    }

    private function getCurrentBatchNumber(): int
    {
        $query = (new QueryBuilder)->select(['MAX(batch) AS batch_max'])->from('migrations');
        return (int) $this->connection->query($query)[0]['batch_max'] ?? 0;
    }

    private function getNextBatchNumber(): int
    {
        return $this->getCurrentBatchNumber() + 1;
    }

    private function getMigrationsBy(string $key): array
    {
        $migrations = [];
        $query = (new QueryBuilder)->select()->from('migrations');
        foreach($this->connection->query($query) as $migration) {
            $migrations[$migration[$key]] = $migration;
        }
        return $migrations;
    }

    public function getMigrations(): array
    {
        return $this->getMigrationsBy('identifier');
    }

    private function getMigrationByName(string $name): ?array
    {
        return $this->getMigrationsBy('name')[$name] ?? null;
    }

    public function hasMigration(string $name): bool
    {
        return $this->getMigrationByName($name) !== null;
    }

    public function hasMigrationRolledBack(string $name): bool
    {
        $migration = $this->getMigrationByName($name);
        return (bool) $migration['rolled_back'];
    }

    public function isMigrationActive(string $name): bool
    {
        return $this->hasMigration($name) && !$this->hasMigrationRolledBack($name);
    }

    private function setupMigrationsTable(): void
    {
        $this->connection->sql("CREATE TABLE `migrations` (
            `id` VARCHAR(36) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `identifier` TEXT NOT NULL,
            `batch` INT NOT NULL DEFAULT 0,
            `rolled_back` INT NOT NULL DEFAULT 0,
            `rolled_back_at` DATETIME NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `modified_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        )
        COLLATE='latin1_swedish_ci'");
    }
}