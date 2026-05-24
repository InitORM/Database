<?php

declare(strict_types=1);

namespace InitORM\Database\Tests;

use InitORM\Database\Database;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\Database\Tests\Support\SqliteHelper;
use PHPUnit\Framework\TestCase;

abstract class AbstractDatabaseTestCase extends TestCase
{
    protected ConnectionInterface $connection;
    protected DatabaseInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = SqliteHelper::makeConnection();
        SqliteHelper::seedUsers($this->connection);
        $this->db = new Database($this->connection);
    }
}
