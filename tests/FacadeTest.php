<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Database;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Facade\DB;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\Database\Tests\Support\SqliteHelper;
use PHPUnit\Framework\TestCase;

final class FacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::replaceImmutable(null);
    }

    public function test_get_database_before_init_throws(): void
    {
        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function test_create_immutable_then_get(): void
    {
        $instance = DB::createImmutable(SqliteHelper::makeConnection());
        self::assertSame($instance, DB::getDatabase());
        self::assertInstanceOf(DatabaseInterface::class, $instance);
    }

    public function test_connect_does_not_touch_facade_slot(): void
    {
        $first = DB::connect(SqliteHelper::makeConnection());
        self::assertInstanceOf(DatabaseInterface::class, $first);

        // The facade slot is still empty since connect() doesn't set it.
        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function test_replace_immutable_accepts_a_database_instance(): void
    {
        $custom = SqliteHelper::makeDatabase();
        $result = DB::replaceImmutable($custom);

        self::assertSame($custom, $result);
        self::assertSame($custom, DB::getDatabase());
    }

    public function test_replace_immutable_with_null_clears_facade(): void
    {
        DB::createImmutable(SqliteHelper::makeConnection());
        self::assertNull(DB::replaceImmutable(null));

        $this->expectException(DatabaseException::class);
        DB::getDatabase();
    }

    public function test_static_call_forwards_to_underlying_database(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        DB::createImmutable($connection);

        $rows = DB::read('users')->asAssoc()->rows();
        self::assertCount(3, $rows);
    }

    public function test_facade_constructor_is_private(): void
    {
        $reflection  = new \ReflectionClass(DB::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
