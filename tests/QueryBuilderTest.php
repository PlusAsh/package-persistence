<?php declare(strict_types=1);

use AshleyHardy\Persistence\Connections\MySQL;
use AshleyHardy\Persistence\Query\Paginator;
use AshleyHardy\Persistence\Query\QueryBuilder;
use AshleyHardy\Utilities\Cal;
use AshleyHardy\Utilities\Ident;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
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

    public function tearDown(): void
    {
        QueryBuilder::reset();
    }

    public static function getBuilder(): QueryBuilder
    {
        return new QueryBuilder(self::getConnection());
    }

    public function testBuildsSimpleSelect(): void
    {
        QueryBuilder::reset();

        $query = self::getBuilder();

        $query->select()->from('test')->where('id = ?', 'yakka-dee');

        $this->assertEquals(
            'SELECT * FROM `test` WHERE id = ?',
            $query->sql(),
            'The generated SQL statement does not meet the expected standard.'
        );

        $this->assertEquals(
            'yakka-dee',
            $query->params()[0],
            'The generated SQL statement does not include the expected parameter.'
        );

        $query = self::getBuilder();

        $query->select()->from('test')->where('id = ?', 'yakka-dee')->limit(1);

        $this->assertEquals(
            'SELECT * FROM `test` WHERE id = ? LIMIT 1',
            $query->sql(),
            'The generated SQL statement does not meet the expected standard.'
        );
    }

    public function testBuildsComplexSelect(): void
    {
        QueryBuilder::reset();

        $query = self::getBuilder();
        $ashley = 'ashley';
        $query->select(['hello', 'world'])->from('test')->where('name = ?', $ashley);

        $this->assertEquals(
            'SELECT hello,world FROM `test` WHERE name = ?',
            $query->sql(),
            'The complex select build did not complete as expected.'
        );

        $this->assertEquals(
            $ashley,
            $query->params()[0],
            'The generated SQL statement does not include the expected parameter.'
        );
    }

    public function testBuildsInsert(): void
    {
        QueryBuilder::reset();
        
        $query = self::getBuilder();

        $query->insert(['hello' => 'world', 'whaddup' => 'people'])->into('test');

        $this->assertEquals(
            'INSERT INTO `test`(hello,whaddup) VALUES (?,?)',
            $query->sql(),
            'Insertion SQL invalid.'
        );

        $this->assertEquals(
            'world',
            $query->params()[0],
            'Insertion SQL invalid.'
        );
    }

    public function testBuildsUpdate(): void
    {
        QueryBuilder::reset();

        $query = self::getBuilder();

        $query->update(['hello' => 'world'])->into('test')->where('id = ?', 1);

        $this->assertEquals(
            'UPDATE `test` SET `hello` = ? WHERE id = ?',
            $query->sql(),
            'Update SQL invalid.'
        );

        $this->assertEquals(
            'world1',
            $query->params()[0] . $query->params()[1],
            'Update SQL invalid.'
        );
    }

    public function testBuildsDelete(): void
    {
        QueryBuilder::reset();

        $query = self::getBuilder();

        $query->delete()->from('test')->where('id = ?', 1);

        $this->assertEquals(
            'DELETE FROM `test` WHERE id = ?',
            $query->sql(),
            'Delete SQL invalid.'
        );

        $this->assertEquals(
            1,
            $query->params()[0],
            'Delete SQL invalid.'
        );
    }

    public function testRunsQueryWithMySQLWithDefaults(): void
    {
        QueryBuilder::reset();

        QueryBuilder::addFilter('INSERT', function(QueryBuilder $qb) {
            $qb->column('id', Ident::uuid(), true)->column('created_at', Cal::now(), true)->column('modified_at', Cal::now(), true);
        });

        $conn = self::getConnection();
        $result = (new QueryBuilder($conn))->insert(['data' => 'sup'])->into('test')->run();
        
        $this->assertTrue(Ident::isUuid($result), 'The test insertion with the Query builder completed successfully.');

        //Fetch the data we just inserted...
        $originalData = (new QueryBuilder($conn))->select()->from('test')->where('id = ?', $result)->run()[0] ?? null;
        $this->assertIsArray(
            $originalData,
            'Failed to retrieve inserted data using the query builder.'
        );

        QueryBuilder::addFilter('UPDATE', function(QueryBuilder $qb) {
            $qb->column('modified_at', Cal::now(), true);
        });

        $result = (new QueryBuilder($conn))->update(['data' => 'double-sup'])->into('test')->where('id = ?', $result)->run();
        $newData = (new QueryBuilder($conn))->select()->from('test')->where('id = ?', $result)->run()[0] ?? null;
        
        $this->assertNotsame(
            $originalData['data'],
            $newData['data'] ?? null,
            'The update did not update a record.'
        );
    }

    public function testExceptionWhenDefiningDuplicateColumns(): void
    {
        $this->expectException(RuntimeException::class);

        $qb = new QueryBuilder(self::getConnection());
        $qb->column('test', 'sup');
        $qb->column('test', 'double-sup');
    }

    public function testPaginator(): void
    {
        QueryBuilder::reset();

        QueryBuilder::addFilter('INSERT', function(QueryBuilder $qb) {
            $qb->column('id', Ident::uuid(), true)->column('created_at', Cal::now(), true)->column('modified_at', Cal::now(), true);
        });

        for($i = 0; $i < 50; $i++) {
            (new QueryBuilder(self::getConnection()))->insert(['data' => 'paginator-test'])->into('test')->run();
        }

        $paginator = (new Paginator)->setConnection(self::getConnection())->from('test')->where('data LIKE \'%paginator%\'');
        //var_dump($paginator->run());
    }
}