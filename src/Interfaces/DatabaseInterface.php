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
namespace InitORM\Database\Interfaces;

use PDO;
use Closure;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\DBAL\Connection\Exceptions\ConnectionException;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\DBAL\DataMapper\Exceptions\DataMapperException;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\QueryBuilderInterface;

/**
 * @mixin QueryBuilderInterface
 */
interface DatabaseInterface
{

    /**
     * @param array|ConnectionInterface $connection
     * @throws DatabaseInvalidArgumentException
     */
    public function __construct($connection);

    /**
     * @return self
     */
    public function builder(): self;

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;

    /**
     * @return PDO
     * @throws ConnectionException
     */
    public function getPDO(): PDO;

    /**
     * @param string $sqlQuery
     * @param array|null $parameters
     * @param array|null $options
     * @return DataMapperInterface
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function query(string $sqlQuery, ?array $parameters = null, ?array $options = null): DataMapperInterface;

    /**
     * @param string|null $table
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function create(?string $table = null, ?array $set = null): bool;

    /**
     * @param string|null $table
     * @param array|null $set
     * @return bool
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function createBatch(?string $table = null, ?array $set = null): bool;

    /**
     * @param string|null $table
     * @param array|null $selectors
     * @param array|null $conditions
     * @return DataMapperInterface
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function read(?string $table = null, ?array $selectors = null, ?array $conditions = null): DataMapperInterface;

    /**
     * @param string|null $table
     * @param array|null $set
     * @param array|null $conditions
     * @return bool
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function update(?string $table = null, ?array $set = null, ?array $conditions = null): bool;

    /**
     * @param string $referenceColumn
     * @param string|null $table
     * @param array|null $set
     * @param array|null $conditions
     * @return bool
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function updateBatch(string $referenceColumn, ?string $table = null, ?array $set = null, ?array $conditions = null): bool;

    /**
     * @param string|null $table
     * @param array|null $conditions
     * @return bool
     * @throws QueryBuilderException
     * @throws DataMapperException
     * @throws ConnectionException
     */
    public function delete(?string $table, ?array $conditions = null): bool;

    /**
     * @param Closure $closure
     * @param int $attempt
     * @param bool $testMode
     * @return bool
     * @throws ConnectionException
     * @throws DatabaseException
     * @throws ConnectionException
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool;

    /**
     * @return int|string|false
     * @throws ConnectionException
     */
    public function insertId();

    /**
     * @return self
     */
    public function enableQueryLog(): self;

    /**
     * @return self
     */
    public function disableQueryLog(): self;

    /**
     * @return array
     */
    public function getQueryLogs(): array;

}
