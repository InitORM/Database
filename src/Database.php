<?php

/**
 * @package InitORM\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\Database;

use Closure;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Connection;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use InitORM\QueryBuilder\QueryBuilderFactory;
use InitORM\QueryBuilder\QueryBuilderFactoryInterface;
use InitORM\QueryBuilder\QueryBuilderInterface;
use PDO;
use Throwable;

use function method_exists;
use function sprintf;

/**
 * Glues a {@see ConnectionInterface} (from initorm/dbal) to a
 * {@see QueryBuilderInterface} (from initorm/query-builder), exposing a CRUD
 * surface plus transactions and query-log proxying.
 *
 * Unknown method calls are forwarded to the inner query builder via
 * {@see self::__call()}; when the builder returns itself (chainable methods)
 * the call is re-wrapped to return this Database so fluent chains span the
 * wrapper boundary.
 *
 * @mixin QueryBuilderInterface
 */
class Database implements DatabaseInterface
{
    private ConnectionInterface $connection;

    private QueryBuilderInterface $builder;

    private QueryBuilderFactoryInterface $queryBuilderFactory;

    /**
     * Last DataMapper produced by an internal CRUD call, used by
     * {@see self::affectedRows()}.
     */
    private ?DataMapperInterface $lastResult = null;

    /**
     * @param array<string, mixed>|ConnectionInterface $connection          Either an
     *        already-built connection or a credentials array suitable for
     *        {@see Connection::__construct()}. PHP's native TypeError fires if
     *        a value of any other type is passed in.
     * @param QueryBuilderFactoryInterface|null        $queryBuilderFactory Injection
     *        hook for tests / DI containers. Defaults to {@see QueryBuilderFactory}.
     */
    public function __construct(
        array|ConnectionInterface $connection,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null
    ) {
        if ($connection instanceof ConnectionInterface) {
            $this->connection = $connection;
        } else {
            $this->connection = new Connection($connection);
        }

        $this->queryBuilderFactory = $queryBuilderFactory ?? new QueryBuilderFactory();
        $this->builder             = $this->queryBuilderFactory->createQueryBuilder($this->connection->getDriver());
    }

    /**
     * Deep-clone the inner builder so spinning off a Database via {@see self::builder()}
     * leaves the original builder state untouched.
     */
    public function __clone(): void
    {
        $this->builder = clone $this->builder;
    }

    /**
     * Forward unknown calls to the inner query builder. When the builder
     * returns itself (chainable methods), the call is re-wrapped to return
     * this Database so fluent chains continue across the wrapper boundary.
     *
     * @param array<int, mixed> $arguments
     *
     * @throws DatabaseException When the method does not exist on the builder.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this->builder, $name)) {
            throw new DatabaseException(sprintf(
                'Method "%s::%s" does not exist.',
                QueryBuilderInterface::class,
                $name
            ));
        }

        $result = $this->builder->{$name}(...$arguments);

        return $result instanceof QueryBuilderInterface ? $this : $result;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function getPDO(): PDO
    {
        return $this->connection->getPDO();
    }

    public function query(string $sqlQuery, ?array $parameters = null, ?array $options = null): DataMapperInterface
    {
        return $this->connection->query($sqlQuery, $parameters, $options);
    }

    /**
     * Spawn a sibling Database that shares this connection but carries a
     * fresh builder — useful for composing independent queries off the same
     * live connection (e.g. a sub-query under a parent query).
     */
    public function withFreshBuilder(): DatabaseInterface
    {
        $clone          = clone $this;
        $clone->builder = $this->queryBuilderFactory->createQueryBuilder($this->connection->getDriver());

        return $clone;
    }

    /**
     * @deprecated since 3.0 — use {@see self::withFreshBuilder()} instead. Kept
     *             as a thin alias for the duration of the v3 line.
     */
    public function builder(): DatabaseInterface
    {
        return $this->withFreshBuilder();
    }

    public function create(?string $table = null, ?array $set = null): bool
    {
        if (!empty($table)) {
            $this->builder->from($table);
        }
        if (!empty($set)) {
            $this->builder->set($set);
        }

        $this->executeBuilderQuery($this->builder->generateInsertQuery());

        return true;
    }

    public function createBatch(?string $table = null, ?array $set = null): bool
    {
        if (!empty($table)) {
            $this->builder->from($table);
        }
        if (!empty($set)) {
            foreach ($set as $row) {
                $this->builder->set($row);
            }
        }

        $this->executeBuilderQuery($this->builder->generateBatchInsertQuery());

        return true;
    }

    public function read(
        ?string $table = null,
        ?array $selectors = null,
        ?array $conditions = null
    ): DataMapperInterface {
        if (!empty($table)) {
            $this->builder->from($table);
        }

        // generateSelectQuery($selectors, $conditions) registers parameters
        // via where(); the snapshot MUST be taken AFTER compile, not before.
        $sql        = $this->builder->generateSelectQuery($selectors ?? [], $conditions ?? []);
        $parameters = $this->builder->getParameter()->all();

        try {
            $this->lastResult = $this->connection->query($sql, $parameters);
        } finally {
            $this->builder->resetStructure();
            $this->builder->getParameter()->reset();
        }

        return $this->lastResult;
    }

    public function update(?string $table = null, ?array $set = null, ?array $conditions = null): bool
    {
        if (!empty($table)) {
            $this->builder->from($table);
        }
        if (!empty($set)) {
            $this->builder->set($set);
        }

        $this->applyConditions($conditions);

        $this->executeBuilderQuery($this->builder->generateUpdateQuery());

        return true;
    }

    public function updateBatch(
        string $referenceColumn,
        ?string $table = null,
        ?array $set = null,
        ?array $conditions = null
    ): bool {
        if (!empty($table)) {
            $this->builder->from($table);
        }
        if (!empty($set)) {
            foreach ($set as $row) {
                $this->builder->set($row);
            }
        }

        $this->applyConditions($conditions);

        $this->executeBuilderQuery($this->builder->generateUpdateBatchQuery($referenceColumn));

        return true;
    }

    public function delete(?string $table = null, ?array $conditions = null): bool
    {
        if (!empty($table)) {
            $this->builder->from($table);
        }

        $this->applyConditions($conditions);

        $this->executeBuilderQuery($this->builder->generateDeleteQuery());

        return true;
    }

    /**
     * @throws DatabaseException When a transaction is already open, or when all
     *         retry attempts fail. The original {@see Throwable} is preserved
     *         as the previous-exception chain.
     */
    public function transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool
    {
        if ($attempt < 1) {
            throw new DatabaseInvalidArgumentException('The number of transaction attempts cannot be less than 1.');
        }

        $pdo = $this->connection->getPDO();
        if ($pdo->inTransaction()) {
            throw new DatabaseException('Cannot start a transaction while another is already in progress.');
        }

        $lastError = null;
        for ($i = 0; $i < $attempt; ++$i) {
            try {
                $pdo->beginTransaction();
                $closure($this);

                $committed = $testMode ? $pdo->rollBack() : $pdo->commit();

                if ($committed) {
                    return true;
                }
            } catch (Throwable $e) {
                $lastError = $e;
                // beginTransaction()/commit() can throw mid-flight; we may or
                // may not still be in a transaction at this point. Guard the
                // rollback to avoid PDO's "no active transaction" error.
                // @phpstan-ignore-next-line booleanNot.exprNotBoolean
                if ($pdo->inTransaction() === true) {
                    try {
                        $pdo->rollBack();
                    } catch (Throwable $rollbackError) {
                        // The original error is what users care about; surface
                        // a rollback failure only as part of the message.
                        $lastError = new DatabaseException(
                            sprintf(
                                'Rollback failed after a failed transaction attempt: %s (original: %s)',
                                $rollbackError->getMessage(),
                                $e->getMessage()
                            ),
                            (int) $e->getCode(),
                            $e
                        );
                    }
                }
            }
        }

        throw new DatabaseException(
            sprintf('Transaction failed after %d attempt(s): %s', $attempt, $lastError?->getMessage() ?? 'unknown'),
            $lastError !== null ? (int) $lastError->getCode() : 0,
            $lastError
        );
    }

    /**
     * Forward to {@see \PDO::lastInsertId()}.
     *
     * @return string|false False when the driver does not provide one (or no
     *         row has been inserted on this connection yet).
     */
    public function insertId(): string|false
    {
        return $this->connection->getPDO()->lastInsertId();
    }

    /**
     * Number of rows affected by the most recent CRUD call on this Database
     * instance (create / createBatch / update / updateBatch / delete / read).
     * Returns 0 when no CRUD call has executed yet.
     *
     * Driver caveat: {@see \PDOStatement::rowCount()} is unreliable for SELECT
     * on drivers that don't buffer results (e.g. unbuffered MySQL, SQLite for
     * SELECT). For INSERT/UPDATE/DELETE on common drivers it returns the
     * affected-row count, which is the intended use here.
     */
    public function affectedRows(): int
    {
        return $this->lastResult?->numRows() ?? 0;
    }

    public function enableQueryLog(): static
    {
        $this->connection->setQueryLogs(true);

        return $this;
    }

    public function disableQueryLog(): static
    {
        $this->connection->setQueryLogs(false);

        return $this;
    }

    public function getQueryLogs(): array
    {
        return $this->connection->getQueryLogs();
    }

    /**
     * Compile-and-execute a SQL string produced by the builder, then reset the
     * builder's parameter bag so a follow-up CRUD call starts clean.
     */
    private function executeBuilderQuery(string $sql): DataMapperInterface
    {
        $parameters = $this->builder->getParameter()->all();

        try {
            $this->lastResult = $this->connection->query($sql, $parameters);
        } finally {
            // Wipe builder state so the next CRUD call starts with a clean
            // structure (no leftover where/from/select/set buckets).
            $this->builder->resetStructure();
            $this->builder->getParameter()->reset();
        }

        return $this->lastResult;
    }

    /**
     * Translate the CRUD-helper $conditions shape into builder where() calls.
     *
     * - String keys → where(key, '=', value)
     * - Integer keys → where(value) (caller-supplied RawQuery or column name)
     *
     * @param array<int|string, mixed>|null $conditions
     */
    private function applyConditions(?array $conditions): void
    {
        if (empty($conditions)) {
            return;
        }
        foreach ($conditions as $column => $value) {
            if (is_string($column)) {
                $this->builder->where($column, '=', $value);
            } else {
                $this->builder->where($value);
            }
        }
    }
}
