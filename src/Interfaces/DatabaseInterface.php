<?php

/**
 * @package InitORM\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\Database\Interfaces;

use Closure;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\DBAL\Connection\Exceptions\ConnectionException;
use InitORM\DBAL\Connection\Exceptions\SQLExecuteException;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\DBAL\DataMapper\Exceptions\DataMapperException;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\QueryBuilderInterface;
use PDO;

/**
 * Public surface of a {@see \InitORM\Database\Database}. Composes a
 * {@see ConnectionInterface} (from initorm/dbal) with a
 * {@see QueryBuilderInterface} (from initorm/query-builder) and exposes a
 * CRUD-shaped API, plus transactions, query-log proxying, and raw query
 * execution.
 *
 * Unknown method calls are forwarded to the inner query builder — the
 * {@code @mixin} hint lets IDEs/PHPStan see the full builder surface through
 * any DatabaseInterface-typed variable.
 *
 * @mixin QueryBuilderInterface
 */
interface DatabaseInterface
{
    /**
     * The underlying DBAL connection. Useful for accessing PDO directly or
     * for sharing the connection across sibling databases (e.g. via
     * {@see self::withFreshBuilder()}).
     */
    public function getConnection(): ConnectionInterface;

    /**
     * Shortcut for {@code $db->getConnection()->getPDO()} — opens the
     * underlying PDO connection on demand.
     *
     * @throws ConnectionException When PDO cannot connect.
     */
    public function getPDO(): PDO;

    /**
     * Prepare and execute a raw SQL statement. The result wraps the underlying
     * {@see \PDOStatement} in a fluent {@see DataMapperInterface} for
     * row/object fetching.
     *
     * @param array<string, scalar|null>|null $parameters Named parameters; the
     *        leading `:` on each key is optional.
     * @param array<int, mixed>|null          $options    PDO prepare options.
     *
     * @throws SQLExecuteException When the statement fails to prepare/execute.
     * @throws ConnectionException When the connection cannot be established.
     */
    public function query(string $sqlQuery, ?array $parameters = null, ?array $options = null): DataMapperInterface;

    /**
     * Spawn a sibling Database that shares this connection but carries a
     * fresh, empty builder. Useful when composing independent queries on the
     * same live connection without leaking builder state.
     */
    public function withFreshBuilder(): self;

    /**
     * @deprecated since 3.0 — use {@see self::withFreshBuilder()}.
     */
    public function builder(): self;

    /**
     * Compile any pending builder state into an INSERT and execute it.
     *
     * @param string|null              $table Optional FROM table; when null
     *        the table must already be set on the builder via {@code from()}.
     * @param array<string, mixed>|null $set  Optional column → value map for
     *        the row to insert.
     *
     * @return bool True on successful execution. (Throws on failure rather
     *              than returning false, so a true return is unambiguous.)
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function create(?string $table = null, ?array $set = null): bool;

    /**
     * Compile any pending builder state into a multi-row INSERT and execute it.
     *
     * @param array<int, array<string, mixed>>|null $set One row per element.
     *
     * @return bool True on successful execution.
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function createBatch(?string $table = null, ?array $set = null): bool;

    /**
     * Compile any pending builder state into a SELECT and execute it.
     *
     * @param array<int, string|\InitORM\QueryBuilder\RawQuery>|null $selectors
     *        Optional columns. When null, the previously-configured select list
     *        (or "*") is used.
     * @param array<int|string, mixed>|null                          $conditions
     *        Optional WHERE shortcuts: string-keyed entries become
     *        {@code where(key, '=', value)}; integer-keyed entries are passed
     *        as a single argument to {@code where()}.
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function read(
        ?string $table = null,
        ?array $selectors = null,
        ?array $conditions = null
    ): DataMapperInterface;

    /**
     * Compile any pending builder state into an UPDATE and execute it.
     *
     * @param array<string, mixed>|null     $set        Columns to update.
     * @param array<int|string, mixed>|null $conditions See {@see self::read()}.
     *
     * @return bool True on successful execution.
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function update(?string $table = null, ?array $set = null, ?array $conditions = null): bool;

    /**
     * Compile any pending builder state into a CASE/WHEN-based batch UPDATE
     * keyed by {@code $referenceColumn} and execute it.
     *
     * @param array<int, array<string, mixed>>|null $set        One row per element.
     * @param array<int|string, mixed>|null         $conditions See {@see self::read()}.
     *
     * @return bool True on successful execution.
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function updateBatch(
        string $referenceColumn,
        ?string $table = null,
        ?array $set = null,
        ?array $conditions = null
    ): bool;

    /**
     * Compile any pending builder state into a DELETE and execute it.
     *
     * @param array<int|string, mixed>|null $conditions See {@see self::read()}.
     *
     * @return bool True on successful execution.
     *
     * @throws QueryBuilderException
     * @throws SQLExecuteException
     * @throws ConnectionException
     * @throws DataMapperException
     */
    public function delete(?string $table = null, ?array $conditions = null): bool;

    /**
     * Run {@code $closure} inside a transaction with optional retries.
     *
     * The closure receives this Database instance as its only argument and is
     * expected to throw to abort the transaction. On exception, the current
     * transaction is rolled back and the next attempt begins. When all attempts
     * fail, the last error is re-thrown wrapped in a {@see DatabaseException}.
     *
     * When {@code $testMode} is true the transaction is rolled back instead of
     * committed even on success — useful for integration tests that want to
     * exercise the closure without persisting changes.
     *
     * @throws DatabaseInvalidArgumentException When {@code $attempt < 1}.
     * @throws DatabaseException                When a transaction is already
     *         in progress, or when all retries fail.
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool;

    /**
     * Forward to {@see \PDO::lastInsertId()}.
     *
     * @return string|false False when the driver does not support sequences
     *         or no row has been inserted on this connection yet.
     *
     * @throws ConnectionException
     */
    public function insertId(): string|false;

    /**
     * Number of rows affected by the most recent CRUD call on this Database
     * (create/createBatch/update/updateBatch/delete/read). Returns 0 when no
     * CRUD call has executed yet.
     */
    public function affectedRows(): int;

    /**
     * Enable the connection-level query log. Each subsequent statement is
     * recorded with its parameters and execution time, retrievable via
     * {@see self::getQueryLogs()}.
     */
    public function enableQueryLog(): static;

    /**
     * Disable the query log without clearing previously-collected entries.
     */
    public function disableQueryLog(): static;

    /**
     * @return array<int, array{query: string, args: array<string, mixed>|null, timer: float}>
     */
    public function getQueryLogs(): array;
}
