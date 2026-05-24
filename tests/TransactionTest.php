<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\Database\Tests\Support\SqliteHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionTest extends TestCase
{
    public function test_commit_persists_changes(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $result = $db->transaction(function ($db) {
            $db->create('users', ['name' => 'T', 'email' => 't@example.com', 'active' => 1, 'score' => 1]);
        });

        self::assertTrue($result);
        self::assertCount(4, $db->read('users')->asAssoc()->rows());
    }

    public function test_exception_rolls_back_and_wraps_original(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        try {
            $db->transaction(function ($db) {
                $db->create('users', ['name' => 'X', 'email' => 'x@example.com', 'active' => 1, 'score' => 0]);
                throw new RuntimeException('rollback me');
            });
            self::fail('Expected DatabaseException');
        } catch (DatabaseException $e) {
            self::assertSame('rollback me', $e->getPrevious()?->getMessage());
        }

        self::assertCount(3, $db->read('users')->asAssoc()->rows(), 'INSERT inside the failed tx must have rolled back.');
    }

    public function test_nested_transaction_call_throws(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $caught = null;
        try {
            $db->transaction(function ($db) {
                // Trying to open another transaction on the same PDO while
                // one is already in flight must fail clearly.
                $db->transaction(static fn () => null);
            });
        } catch (DatabaseException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Transaction failed', $caught->getMessage());
    }

    public function test_invalid_attempt_throws_invalid_argument(): void
    {
        $db = SqliteHelper::makeDatabase();
        $this->expectException(DatabaseInvalidArgumentException::class);
        $db->transaction(static fn () => null, attempt: -1);
    }

    public function test_test_mode_rolls_back_even_on_success(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $result = $db->transaction(function ($db) {
            $db->create('users', ['name' => 'Z', 'email' => 'z@example.com', 'active' => 1, 'score' => 0]);
        }, testMode: true);

        self::assertTrue($result);
        self::assertCount(3, $db->read('users')->asAssoc()->rows(), 'testMode must roll back even though the closure succeeded.');
    }
}
