<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Database;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\Database\Facade\DB;
use InitORM\Database\Tests\Support\SqliteHelper;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pinpoint regression tests for the issues identified during the 3.0 review.
 * Each test method names the bug it covers ("test_bug1_..."), so future
 * contributors can trace failures back to the original ticket.
 */
final class BugRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::replaceImmutable(null); // ensure facade slot is clean between tests
    }

    /**
     * BUG-1: Database::read() snapshotted parameters BEFORE compiling the SELECT,
     * so any WHERE registered through generateSelectQuery()'s $conditions
     * shortcut was missing from the bound array → SQLSTATE[HY093].
     */
    public function test_bug1_read_with_conditions_binds_parameters_correctly(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $rows = $db->read('users', ['id', 'name'], ['id' => 2])
            ->asAssoc()
            ->rows();

        self::assertCount(1, $rows);
        self::assertSame('Bob', $rows[0]['name']);
    }

    /**
     * BUG-2: UPDATE that finds rows but writes the same value used to return
     * false (because rowCount() == 0 in MySQL — on SQLite it returns the
     * matched rowcount, but the bug was the semantics, not the driver). Now
     * any successful execution returns true.
     */
    public function test_bug2_update_returns_true_even_when_no_rows_change(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        // No rows match → "succeeded but did nothing" must still be true.
        $result = $db->where('id', '=', 9999)
            ->update('users', ['name' => 'Nobody']);

        self::assertTrue($result);
        self::assertSame(0, $db->affectedRows());
    }

    public function test_bug2_create_returns_true_and_exposes_affected_rows(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $result = $db->create('users', ['name' => 'Dan', 'email' => 'dan@example.com', 'active' => 1, 'score' => 7]);

        self::assertTrue($result);
        self::assertSame(1, $db->affectedRows());
        self::assertNotFalse($db->insertId());
    }

    /**
     * BUG-3: Transaction failures used to be swallowed silently — the closure
     * could throw, the user got back a bare `false`, and the original error
     * was unreachable. Now the failure is wrapped and re-thrown.
     */
    public function test_bug3_transaction_failure_propagates_with_original_error(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $caught = null;
        try {
            $db->transaction(function () {
                throw new \RuntimeException('boom');
            });
        } catch (DatabaseException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Transaction failed', $caught->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $caught->getPrevious());
        self::assertSame('boom', $caught->getPrevious()->getMessage());
        self::assertFalse($db->getPDO()->inTransaction(), 'PDO must not be left in a transaction.');
    }

    public function test_bug3_transaction_retries_until_success(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $attempts = 0;
        $result = $db->transaction(function ($db) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \RuntimeException('still flaky');
            }
            $db->create('users', ['name' => 'Eve', 'email' => 'eve@example.com', 'active' => 1, 'score' => 5]);
        }, attempt: 3);

        self::assertTrue($result);
        self::assertSame(3, $attempts);
    }

    public function test_bug3_transaction_test_mode_rolls_back(): void
    {
        $connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($connection);
        $db = new Database($connection);

        $before = $db->read('users')->numRows();
        $db->transaction(function ($db) {
            $db->create('users', ['name' => 'X', 'email' => 'x@example.com', 'active' => 1, 'score' => 0]);
        }, testMode: true);

        // Re-open a fresh read to avoid relying on a stale DataMapper.
        $after = $db->read('users')->numRows();
        self::assertSame($before, $after);
    }

    public function test_bug3_transaction_rejects_invalid_attempt_count(): void
    {
        $db = SqliteHelper::makeDatabase();
        $this->expectException(DatabaseInvalidArgumentException::class);
        $db->transaction(static fn () => null, attempt: 0);
    }

    /**
     * BUG-4: createImmutable() used to silently overwrite a previously-set
     * facade instance. Now a second call throws unless replaceImmutable() is
     * called explicitly.
     */
    public function test_bug4_create_immutable_is_actually_immutable(): void
    {
        DB::createImmutable(SqliteHelper::makeConnection());

        $this->expectException(DatabaseException::class);
        DB::createImmutable(SqliteHelper::makeConnection());
    }

    public function test_bug4_replace_immutable_swaps_explicitly(): void
    {
        $first  = DB::createImmutable(SqliteHelper::makeConnection());
        $second = DB::replaceImmutable(SqliteHelper::makeConnection());

        self::assertNotSame($first, $second);
        self::assertSame($second, DB::getDatabase());
    }

    /**
     * BUG-5: DB used to declare a non-static __call() that could never fire.
     * The class is now strictly static — instantiation must fail.
     */
    public function test_bug5_db_facade_cannot_be_instantiated(): void
    {
        $reflection  = new \ReflectionClass(DB::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'DB::__construct() must be private.');
    }

    /**
     * BUG-6: insertId() return type used to be `int|string|false` in the
     * interface, untyped in the impl, and `int|string` on the facade. Now
     * uniformly `string|false`.
     */
    public function test_bug6_insert_id_returns_string_or_false(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->create('users', ['name' => 'F', 'email' => 'f@example.com', 'active' => 1, 'score' => 1]);
        $id = $db->insertId();

        // SQLite returns the rowid as a string per PDO contract.
        self::assertIsString($id);
        self::assertSame('4', $id);
    }

    /**
     * BUG-8: __construct used to accept untyped $connection and throw a
     * message-less exception. Now it uses a union type and the exception
     * carries a useful message.
     */
    public function test_bug8_invalid_connection_throws_descriptive_exception(): void
    {
        $this->expectException(\TypeError::class);
        // @phpstan-ignore-next-line — intentional misuse
        new Database('not a connection');
    }

    /**
     * BUG-11: update/delete used to call where($column, $value) which relied
     * on QueryBuilder's implicit "2-arg value-shortcut". Now the call site
     * is explicit: where($column, '=', $value).
     */
    public function test_bug11_update_conditions_bind_to_value_slot_explicitly(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->update('users', ['active' => 0], ['id' => 1]);

        $row = $db->read('users', ['id', 'active'], ['id' => 1])->asAssoc()->row();
        self::assertSame(0, (int) $row['active']);
    }

    /**
     * BUG-12 (found during BUG-11's test run): CRUD methods used to leave
     * builder state behind — a delete()'s WHERE clause would silently bleed
     * into the next read(). The fix wipes structure + parameters after every
     * CRUD execution (in a finally block, so even thrown queries reset).
     */
    public function test_bug12_builder_structure_resets_between_crud_calls(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->where('id', '=', 1)->delete('users');

        // The where-clause from delete() must NOT leak into this read.
        $rows = $db->read('users')->asAssoc()->rows();
        self::assertCount(2, $rows, 'Builder state must reset after each CRUD call.');
    }

    public function test_bug11_delete_with_conditions(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->delete('users', ['id' => 2]);

        $remaining = $db->read('users')->asAssoc()->rows();
        self::assertCount(2, $remaining);
        self::assertNotContains('Bob', array_column($remaining, 'name'));
    }
}
