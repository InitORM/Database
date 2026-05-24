<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Database;
use InitORM\Database\Tests\Support\SqliteHelper;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\QueryBuilder\QueryBuilder;
use InitORM\QueryBuilder\QueryBuilderFactoryInterface;
use InitORM\QueryBuilder\QueryBuilderInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use TypeError;

final class DatabaseConstructionTest extends TestCase
{
    public function test_accepts_credentials_array(): void
    {
        $db = new Database([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'charset'  => '',
        ]);

        self::assertInstanceOf(PDO::class, $db->getPDO());
        self::assertSame('sqlite', $db->getConnection()->getDriver());
    }

    public function test_accepts_prebuilt_connection_interface(): void
    {
        $connection = SqliteHelper::makeConnection();
        $db         = new Database($connection);

        self::assertSame($connection, $db->getConnection());
    }

    public function test_rejects_non_array_non_connection_argument_with_type_error(): void
    {
        $this->expectException(TypeError::class);
        // @phpstan-ignore-next-line — intentional misuse
        new Database('garbage');
    }

    public function test_query_builder_factory_can_be_injected(): void
    {
        $captured = null;

        $factory = new class ($captured) implements QueryBuilderFactoryInterface {
            /** @var mixed */
            private $captured;

            public function __construct(&$captured)
            {
                $this->captured = &$captured;
            }

            public function createQueryBuilder(?string $driver = null): QueryBuilderInterface
            {
                $this->captured = $driver;
                return new QueryBuilder($driver);
            }
        };

        new Database(SqliteHelper::makeConnection(), $factory);
        self::assertSame('sqlite', $captured);
    }

    public function test_with_fresh_builder_isolates_state(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $db->select('id')->where('id', '=', 1);
        $sibling = $db->withFreshBuilder();

        self::assertNotSame($db, $sibling);
        self::assertSame($db->getConnection(), $sibling->getConnection(), 'Connection must be shared.');

        // The sibling's builder is empty: SELECT * with no WHERE.
        $rows = $sibling->read('users')->asAssoc()->rows();
        self::assertCount(3, $rows);
    }

    public function test_legacy_builder_alias_still_works(): void
    {
        $db      = SqliteHelper::makeDatabase();
        $sibling = $db->builder();

        self::assertNotSame($db, $sibling);
        self::assertSame($db->getConnection(), $sibling->getConnection());
    }

    public function test_unknown_method_throws_descriptive_exception(): void
    {
        $db = SqliteHelper::makeDatabase();

        $this->expectException(\InitORM\Database\Exceptions\DatabaseException::class);
        $this->expectExceptionMessageMatches('/QueryBuilderInterface::someBogusMethod/');

        // @phpstan-ignore-next-line — intentional misuse
        $db->someBogusMethod();
    }

    public function test_clone_creates_independent_builder(): void
    {
        $db   = SqliteHelper::makeDatabase();
        $copy = clone $db;

        $db->select('a');
        $copy->select('b');

        $dbSelect   = $db->exportQB()['select'];
        $copySelect = $copy->exportQB()['select'];

        self::assertCount(1, $dbSelect);
        self::assertCount(1, $copySelect);
        self::assertNotSame($dbSelect, $copySelect, 'Cloned builder must hold independent state.');
        self::assertStringContainsString('a', $dbSelect[0]);
        self::assertStringContainsString('b', $copySelect[0]);
    }
}
