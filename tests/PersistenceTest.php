<?php declare(strict_types=1);

use AshleyHardy\Persistence\Connections\MySQL;
use AshleyHardy\Persistence\PersistentEntity;
use AshleyHardy\Persistence\Query\QueryBuilder;
use AshleyHardy\Utilities\Ident;
use PHPUnit\Framework\TestCase;

class PersistenceTest extends TestCase
{
    public static function getConnection(): MySQL
    {
        return new MySQL([
            'username' => $_ENV['TEST_DB_USER'],
            'password' => $_ENV['TEST_DB_PASS'],
            'hostname' => $_ENV['TEST_DB_HOST'],
            'database' => $_ENV['TEST_DB_NAME']
        ]);
    }

    public static function setUpBeforeClass(): void
    {
        $connection = self::getConnection();

        $connection->sql("
            DROP TABLE IF EXISTS `test`;
        ");

        $connection->sql("
        CREATE TABLE `test` (
            `id` VARCHAR(36) NOT NULL COLLATE 'latin1_swedish_ci',
            `data` TEXT NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
            `created_at` DATETIME NOT NULL,
            `modified_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`) USING BTREE
        )
        COLLATE='latin1_swedish_ci'
        ENGINE=InnoDB
        ;        
        ");
    }
    
    public function testPersistenceMySQLInsertionAndUpdateWithArray(): void
    {
        $connection = self::getConnection();

        $testData = [
            'data' => 'Hello World'
        ];

        $query = (new QueryBuilder)->insert($testData)->into('test');
        $results = $connection->query($query);
        $testData['id'] = $query->get('id');
        $testData['created_at'] = $query->get('created_at');
        $testData['modified_at'] = $query->get('created_at');

        $this->assertTrue(
            Ident::isUuid($testData['id']),
            "The insertion did not produce a valid Uuid {$testData['id']} for the test data."
        );

        $this->assertArrayHasKey(
            'created_at',
            $testData,
            'The insertion did not produce a \'created_at\' column for the data.'
        );

        $this->assertArrayHasKey(
            'modified_at',
            $testData,
            'The insertion did not produce a \'modified_at\' column for the data.'
        );

        $testData['data'] = 'Hello world, again.';
        $testData['modified_at'] = 'In the past';
        $originalModifiedAt = $testData['modified_at'];
        $testData['modified_at'] = 'In the future';

        $query = (new QueryBuilder)->update($testData)->into('test')->where('id = ?', $testData['id']);
        $testData['modified_at'] = $query->get('modified_at');

        $this->assertEquals(
            'Hello world, again.',
            $testData['data'],
            'The update call failed to update the test data.'
        );

        $this->assertNotTrue(
            $testData['modified_at'] == $originalModifiedAt,
            'The modified_at array attribute was not updated.'
        );
    }

    /**
     * @group test
     */
    public function testPersistenceMySQLInsertionAndUpdateWithObject(): void
    {
        $connection = self::getConnection();

        $testClass = new class extends PersistentEntity {
            public ?string $id = null;
            public string $data = "Whaddup world";
            public ?string $createdAt = null;
            public ?string $modifiedAt = null;

            public function persist(): array
            {
                return [
                    'data'
                ];
            }

            public static function table(): string
            {
                return 'test';
            }
        };

        $testClass->save($connection);

        $this->assertTrue(
            Ident::isUuid($testClass->id),
            "The insertion did not produce a valid Uuid {$testClass->id} for the test data."
        );

        $this->assertNotEmpty(
            $testClass->createdAt,
            'The insertion did not update the \'created_at\' property'
        );

        $this->assertNotEmpty(
            $testClass->modifiedAt,
            'The insertion did not update the \'created_at\' property'
        );

        $testClass->data = 'I\'ve changed the data, bro.';
        $testClass->modifiedAt = 'In the past';
        $originalModifiedAt = $testClass->modifiedAt;

        $testClass->save($connection);

        $this->assertEquals(
            'I\'ve changed the data, bro.',
            $testClass->data,
            'The update call failed to update the test class.'
        );

        $this->assertNotTrue(
            $testClass->modifiedAt == $originalModifiedAt,
            'The modified_at property was not updated.'
        );

        $fetched = $testClass::fetch($testClass->id, $connection);
        $this->assertEquals(
            $testClass->id,
            $fetched->id,
            'The fetch test did not produce a result matching the ID of the testClass.'
        );
    }

    public function testGetTablesHasTestTable(): void
    {
        $tables = $this->getConnection()->getTables();
        
        $this->assertContains(
            'test',
            $tables,
            'The getTables call failed to validate that the \'test\' table is present in the test database'
        );

        $this->assertTrue(
            $this->getConnection()->tableExists('test'),
            'The tableExists method failed to validate that the \'test\' table is present in the test database'
        );

        $this->assertNotTrue(
            $this->getConnection()->tableExists('plop'),
            'The tableExists method failed to validate that the \'plop\' table is NOT present in the test database'
        );
    }
}