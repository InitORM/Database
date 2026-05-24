<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Tests\Support\SqliteHelper;
use PHPUnit\Framework\TestCase;

final class CrudTest extends TestCase
{
    public function test_create_inserts_row_and_assigns_id(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->create('users', [
            'name'   => 'Dan',
            'email'  => 'dan@example.com',
            'active' => 1,
            'score'  => 7,
        ]);

        self::assertSame('4', $db->insertId());

        $row = $db->read('users', ['name'], ['id' => 4])->asAssoc()->row();
        self::assertSame('Dan', $row['name']);
    }

    public function test_create_batch_inserts_multiple_rows(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->createBatch('users', [
            ['name' => 'D', 'email' => 'd@example.com', 'active' => 1, 'score' => 1],
            ['name' => 'E', 'email' => 'e@example.com', 'active' => 1, 'score' => 2],
            ['name' => 'F', 'email' => 'f@example.com', 'active' => 1, 'score' => 3],
        ]);

        $rows = $db->read('users')->asAssoc()->rows();
        self::assertCount(6, $rows);
    }

    public function test_read_with_builder_chain(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $rows = $db->select('name', 'score')
            ->where('active', '=', 1)
            ->orderBy('score', 'DESC')
            ->read('users')
            ->asAssoc()
            ->rows();

        self::assertCount(2, $rows);
        self::assertSame('Carol', $rows[0]['name']);
        self::assertSame('Alice', $rows[1]['name']);
    }

    public function test_read_with_limit_and_offset(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $rows = $db->orderBy('id', 'ASC')
            ->offset(1)
            ->limit(1)
            ->read('users')
            ->asAssoc()
            ->rows();

        self::assertCount(1, $rows);
        self::assertSame('Bob', $rows[0]['name']);
    }

    public function test_update_modifies_only_matched_rows(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->update('users', ['active' => 0], ['id' => 1]);

        $rows = $db->read('users', ['id', 'active'])->asAssoc()->rows();
        $map  = array_column($rows, 'active', 'id');

        self::assertSame(0, (int) $map[1]);
        self::assertSame(0, (int) $map[2]);
        self::assertSame(1, (int) $map[3]);
    }

    public function test_update_batch_uses_case_when_per_row(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->updateBatch('id', 'users', [
            ['id' => 1, 'score' => 100],
            ['id' => 2, 'score' => 200],
        ]);

        $rows = $db->read('users', ['id', 'score'])->asAssoc()->rows();
        $map  = array_column($rows, 'score', 'id');

        self::assertSame(100, (int) $map[1]);
        self::assertSame(200, (int) $map[2]);
        // The row that wasn't in the batch must keep its previous value.
        self::assertSame(99, (int) $map[3]);
    }

    public function test_delete_removes_only_matched_rows(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->delete('users', ['id' => 2]);

        $rows = $db->read('users')->asAssoc()->rows();
        self::assertCount(2, $rows);
        self::assertNotContains('Bob', array_column($rows, 'name'));
    }

    public function test_raw_query_passes_through_with_parameters(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $row = $db->query(
            'SELECT name FROM users WHERE id = :id',
            [':id' => 3]
        )->asAssoc()->row();

        self::assertSame('Carol', $row['name']);
    }

    public function test_affected_rows_reflects_last_crud_call(): void
    {
        $db = SqliteHelper::makeDatabase();
        SqliteHelper::seedUsers($db->getConnection());

        $db->update('users', ['active' => 0], ['active' => 1]);
        self::assertSame(2, $db->affectedRows()); // Alice + Carol

        $db->delete('users', ['id' => 99999]);
        self::assertSame(0, $db->affectedRows());
    }

    public function test_chained_call_returns_database_not_builder(): void
    {
        $db = SqliteHelper::makeDatabase();

        $returned = $db->select('id')->where('id', '=', 1);
        self::assertSame($db, $returned);
    }
}
