<?php
/**
 * InitORM Database
 *
 * This file is part of InitORM Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2023 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     1.0
 * @link        https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);
namespace InitORM\Database;

use PDO;
use Closure;
use Throwable;
use InitORM\DBAL\Connection\Connection;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use \InitORM\Database\Exceptions\{DatabaseException, DatabaseInvalidArgumentException};
use \InitORM\QueryBuilder\{QueryBuilderFactory, QueryBuilderFactoryInterface, QueryBuilderInterface};

class Database implements DatabaseInterface
{

    private ConnectionInterface $connection;

    private QueryBuilderInterface $builder;

    private QueryBuilderFactoryInterface $queryBuilderFactory;

    /**
     * @inheritDoc
     */
    public function __construct($connection)
    {
        if ($connection instanceof ConnectionInterface) {
            $this->connection = $connection;
        } else if (is_array($connection)) {
            $this->connection = new Connection($connection);
        } else {
            throw new DatabaseInvalidArgumentException();
        }

        $this->queryBuilderFactory = new QueryBuilderFactory();

        $this->builder = $this->queryBuilderFactory->createQueryBuilder();
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws DatabaseException
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->builder, $name)) {
            $res = $this->builder->{$name}(...$arguments);

            return ($res instanceof QueryBuilderInterface) ? $this : $res;
        }

        throw new DatabaseException();
    }

    /**
     * @inheritDoc
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function getPDO(): PDO
    {
        return $this->getConnection()->getPDO();
    }

    /**
     * @inheritDoc
     */
    public function query(string $sqlQuery, ?array $parameters = null, ?array $options = null): DataMapperInterface
    {
        return $this->getConnection()->query($sqlQuery, $parameters, $options);
    }

    public function builder(): DatabaseInterface
    {
        $db = clone $this;
        $db->builder = $this->queryBuilderFactory->createQueryBuilder();

        return $db;
    }

    /**
     * @inheritDoc
     */
    public function create(?string $table = null, ?array $set = null): bool
    {
        !empty($table) && $this->builder->from($table);
        !empty($set) && $this->builder->set($set);

        $res = $this->query($this->builder->generateInsertQuery(), $this->builder->getParameter()->all());

        $this->builder->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function createBatch(?string $table = null, ?array $set = null): bool
    {
        !empty($table) && $this->builder->from($table);
        if (!empty($set)) {
            foreach ($set as $row) {
                $this->builder->set($row);
            }
        }

        $res = $this->query($this->builder->generateBatchInsertQuery(), $this->builder->getParameter()->all());

        $this->builder->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function read(?string $table = null, ?array $selectors = null, ?array $conditions = null): DataMapperInterface
    {
        !empty($table) && $this->builder->from($table);

        $arguments = $this->builder->getParameter()->all();
        $this->builder->getParameter()->reset();

        return $this->query($this->builder->generateSelectQuery($selectors ?? [], $conditions ?? []), $arguments);
    }

    /**
     * @inheritDoc
     */
    public function update(?string $table = null, ?array $set = null, ?array $conditions = null): bool
    {
        !empty($table) && $this->builder->from($table);
        !empty($set) && $this->builder->set($set);

        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $this->builder->where($column, $value);
                } else {
                    $this->builder->where($value);
                }
            }
        }

        $res = $this->query($this->builder->generateUpdateQuery(), $this->builder->getParameter()->all());

        $this->builder->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function updateBatch(string $referenceColumn, ?string $table = null, ?array $set = null, ?array $conditions = null): bool
    {
        !empty($table) && $this->builder->from($table);
        if (!empty($set)) {
            foreach ($set as $row) {
                $this->builder->set($row);
            }
        }
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $this->builder->where($column, $value);
                } else {
                    $this->builder->where($value);
                }
            }
        }

        $res = $this->query($this->builder->generateUpdateBatchQuery($referenceColumn), $this->builder->getParameter()->all());

        $this->builder->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function delete(?string $table, ?array $conditions = null): bool
    {
        !empty($table) && $this->builder->from($table);

        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $this->builder->where($column, $value);
                } else {
                    $this->builder->where($value);
                }
            }
        }

        $res = $this->query($this->builder->generateDeleteQuery(), $this->builder->getParameter()->all());

        $this->builder->getParameter()->reset();

        return $res->numRows() > 0;
    }

    /**
     * @inheritDoc
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool
    {
        if ($attempt < 1) {
            throw new DatabaseInvalidArgumentException("The number of transaction attempts cannot be less than 1.");
        }
        if ($this->getConnection()->getPDO()->inTransaction()) {
            throw new DatabaseException("Without ending one transaction, another cannot be started.");
        }
        $res = false;
        for ($i = 0; $i < $attempt; ++$i) {
            try {
                $this->getConnection()->getPDO()->beginTransaction();
                call_user_func_array($closure, [$this]);
                $res = $testMode
                    ? $this->getConnection()->getPDO()->rollBack()
                    : $this->getConnection()->getPDO()->commit();
                if ($res) {
                    break;
                }
            } catch (Throwable $e) {
                $res = $this->getConnection()->getPDO()->rollBack();
                continue;
            }
        }
        return $res;
    }

    /**
     * @inheritDoc
     */
    public function insertId()
    {
        return $this->getPDO()->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function enableQueryLog(): self
    {
        $this->getConnection()->setQueryLogs(true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function disableQueryLog(): self
    {
        $this->getConnection()->setQueryLogs(false);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getQueryLogs(): array
    {
        return $this->getConnection()->getQueryLogs();
    }

}
