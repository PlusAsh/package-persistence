<?php declare(strict_types=1);

use AshleyHardy\Persistence\Connections\MySQL;
use AshleyHardy\Persistence\Migrations;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    public function setUp(): void
    {
        $connection = self::getConnection();

        if($connection->tableExists('migrations')) {
            $connection->sql('DROP TABLE `migrations`');
        }

        if($connection->tableExists('migration_test')) {
            $connection->sql('DROP TABLE `migration_test`');
        }
    }

    public static function getConnection(): MySQL
    {
        return new MySQL([
            'username' => $_ENV['TEST_DB_USER'],
            'password' => $_ENV['TEST_DB_PASS'],
            'hostname' => $_ENV['TEST_DB_HOST'],
            'database' => $_ENV['TEST_DB_NAME']
        ]);
    }

    public function testMigrationService(): void
    {
        $connection = self::getConnection();

        $service = new Migrations(__DIR__ . "/resources/migrations", $connection);
        $service->execute();

        $this->assertTrue(
            $connection->tableExists('migration_test'),
            'The Migrations service failed to create the migration_test'
        );

        $this->assertTrue(
            $service->hasMigration('Test Migration'),
            'The Migrations service does not indicate that the \'Test Migration\' has ran.'
        );

        $this->assertNotTrue(
            $service->hasMigrationRolledBack('Test Migration'),
            'The Migrations service indicates that the \'Test Migration\' has been rolled back when it has not.'
        );

        $this->assertTrue(
            $service->isMigrationActive('Test Migration'),
            'The Migrations service does not indicate that the \'Test Migration\' is active (migrated and not rolled back).'
        );

        $service->execute(false);
        $this->assertNotTrue(
            $connection->tableExists('migration_test'),
            'The Migrations service failed to rollback (and delete the migration_test).'
        );

        $this->assertTrue(
            $service->hasMigration('Test Migration'),
            'The Migrations service does not indicate that the \'Test Migration\' has ran after rollback.'
        );

        $this->assertTrue(
            $service->hasMigrationRolledBack('Test Migration'),
            'The Migrations service indicates that the \'Test Migration\' has not been rolled back when it has.'
        );

        $this->assertNotTrue(
            $service->isMigrationActive('Test Migration'),
            'The Migrations service does not indicate that the \'Test Migration\' is inactive (migrated but rolled back).'
        );
    }
}