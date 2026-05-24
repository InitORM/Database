<?php

declare(strict_types=1);

namespace InitORM\Database\Tests\Support;

use InitORM\Database\Database;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Connection;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;

final class SqliteHelper
{
    /**
     * Build a fresh SQLite in-memory connection. Each call returns a NEW
     * connection — there's no shared :memory: across PDO handles, so any
     * schema/data must be applied to the same handle the test will use.
     *
     * @param array<string, mixed> $overrides
     */
    public static function makeConnection(array $overrides = []): ConnectionInterface
    {
        return new Connection(array_merge([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'charset'  => '',
        ], $overrides));
    }

    public static function makeDatabase(array $overrides = []): DatabaseInterface
    {
        return new Database(self::makeConnection($overrides));
    }

    public static function seedUsers(ConnectionInterface $connection): void
    {
        $pdo = $connection->getPDO();
        $pdo->exec(
            'CREATE TABLE users (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                name   TEXT    NOT NULL,
                email  TEXT    NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                score  INTEGER
            )'
        );
        $pdo->exec(
            "INSERT INTO users (name, email, active, score) VALUES
                ('Alice', 'alice@example.com', 1, 42),
                ('Bob',   'bob@example.com',   0, 13),
                ('Carol', 'carol@example.com', 1, 99)"
        );
    }

    public static function seedPosts(ConnectionInterface $connection): void
    {
        $pdo = $connection->getPDO();
        $pdo->exec(
            'CREATE TABLE posts (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id   INTEGER NOT NULL,
                title     TEXT    NOT NULL,
                content   TEXT
            )'
        );
    }
}
