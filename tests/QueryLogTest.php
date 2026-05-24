<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Tests\Support\SqliteHelper;
use PHPUnit\Framework\TestCase;

final class QueryLogTest extends TestCase
{
    public function test_query_log_is_disabled_by_default(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->read('users');
        self::assertSame([], $db->getQueryLogs());
    }

    public function test_enabling_query_log_records_each_query(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->enableQueryLog();
        $db->read('users', ['id'], ['id' => 1]);
        $db->read('users', ['id'], ['id' => 2]);

        $logs = $db->getQueryLogs();
        self::assertCount(2, $logs);
        self::assertArrayHasKey('query', $logs[0]);
        self::assertArrayHasKey('args', $logs[0]);
        self::assertArrayHasKey('timer', $logs[0]);
        self::assertIsFloat($logs[0]['timer']);
        self::assertStringStartsWith('SELECT', $logs[0]['query']);
    }

    public function test_disabling_query_log_stops_recording(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->enableQueryLog();
        $db->read('users');
        $db->disableQueryLog();
        $db->read('users');

        self::assertCount(1, $db->getQueryLogs(), 'Only the read while logging was enabled should be recorded.');
    }

    public function test_enable_disable_return_database_for_chaining(): void
    {
        $db = SqliteHelper::makeDatabase();
        self::assertSame($db, $db->enableQueryLog());
        self::assertSame($db, $db->disableQueryLog());
    }
}
