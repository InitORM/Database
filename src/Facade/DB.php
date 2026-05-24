<?php

/**
 * @package InitORM\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\Database\Facade;

use Closure;
use InitORM\Database\Database;
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Exceptions\DatabaseInvalidArgumentException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use InitORM\QueryBuilder\ParameterInterface;
use InitORM\QueryBuilder\QueryBuilderInterface;
use InitORM\QueryBuilder\RawQuery;
use PDO;

/**
 * Static facade over a single shared {@see DatabaseInterface} instance —
 * convenient for application code that doesn't want to thread a Database
 * around. Call {@see self::createImmutable()} once during bootstrap, then
 * use `DB::select(...)`, `DB::create(...)`, etc.
 *
 * The "@method static …" list mirrors {@see DatabaseInterface} (which itself
 * mixes in {@see QueryBuilderInterface}); the list is reproduced here so IDEs
 * autocomplete the full surface against the bare class name.
 *
 * @method static ConnectionInterface getConnection()
 * @method static PDO getPDO()
 * @method static DataMapperInterface query(string $sqlQuery, ?array $parameters = null, ?array $options = null)
 * @method static DatabaseInterface withFreshBuilder()
 * @method static DatabaseInterface builder()
 * @method static bool create(?string $table = null, ?array $set = null)
 * @method static bool createBatch(?string $table = null, ?array $set = null)
 * @method static DataMapperInterface read(?string $table = null, ?array $selectors = null, ?array $conditions = null)
 * @method static bool update(?string $table = null, ?array $set = null, ?array $conditions = null)
 * @method static bool updateBatch(string $referenceColumn, ?string $table = null, ?array $set = null, ?array $conditions = null)
 * @method static bool delete(?string $table = null, ?array $conditions = null)
 * @method static bool transaction(Closure $closure, int $attempt = 1, bool $testMode = false)
 * @method static string|false insertId()
 * @method static int affectedRows()
 * @method static DatabaseInterface enableQueryLog()
 * @method static DatabaseInterface disableQueryLog()
 * @method static array getQueryLogs()
 *
 * QueryBuilder surface (forwarded via Database::__call):
 *
 * @method static ParameterInterface getParameter()
 * @method static DatabaseInterface setParameter(string $key, mixed $value)
 * @method static DatabaseInterface setParameters(array $parameters = [])
 * @method static DatabaseInterface select(string|RawQuery ...$columns)
 * @method static DatabaseInterface clearSelect()
 * @method static DatabaseInterface selectCount(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectCountDistinct(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectMax(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectMin(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectAvg(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectAs(RawQuery|string $column, string $alias)
 * @method static DatabaseInterface selectUpper(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectLower(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectLength(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null)
 * @method static DatabaseInterface selectLeft(RawQuery|string $column, int $length, ?string $alias = null)
 * @method static DatabaseInterface selectRight(RawQuery|string $column, int $length, ?string $alias = null)
 * @method static DatabaseInterface selectDistinct(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null)
 * @method static DatabaseInterface selectSum(RawQuery|string $column, ?string $alias = null)
 * @method static DatabaseInterface selectConcat(array $columns, ?string $alias = null)
 * @method static DatabaseInterface from(RawQuery|string $table, ?string $alias = null)
 * @method static DatabaseInterface addFrom(RawQuery|string $table, ?string $alias = null)
 * @method static DatabaseInterface table(string|RawQuery $table)
 * @method static DatabaseInterface groupBy(string|RawQuery|array ...$columns)
 * @method static DatabaseInterface orderBy(RawQuery|string $column, string $soft = 'ASC')
 * @method static DatabaseInterface offset(int $offset = 0)
 * @method static DatabaseInterface limit(int $limit)
 * @method static DatabaseInterface join(RawQuery|string $table, RawQuery|string|Closure|null $onStmt = null, string $type = 'INNER')
 * @method static DatabaseInterface selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface naturalJoin(string|RawQuery $table)
 * @method static DatabaseInterface where(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface having(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface on(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface andWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null)
 * @method static DatabaseInterface orWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null)
 * @method static DatabaseInterface set(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static DatabaseInterface addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static DatabaseInterface between(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND')
 * @method static DatabaseInterface orBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static DatabaseInterface andBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static DatabaseInterface notBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND')
 * @method static DatabaseInterface orNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static DatabaseInterface andNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null)
 * @method static DatabaseInterface findInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface andFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface orFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface notFindInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface andNotFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface orNotFindInSet(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface whereIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface whereNotIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface orWhereIn(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface orWhereNotIn(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface andWhereIn(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface andWhereNotIn(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface regexp(string|RawQuery $column, string|RawQuery $value, string $logical = 'AND')
 * @method static DatabaseInterface andRegexp(string|RawQuery $column, string|RawQuery $value)
 * @method static DatabaseInterface orRegexp(string|RawQuery $column, string|RawQuery $value)
 * @method static DatabaseInterface soundex(string|RawQuery $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface andSoundex(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface orSoundex(string|RawQuery $column, mixed $value = null)
 * @method static DatabaseInterface whereIsNull(string|RawQuery $column, string $logical = 'AND')
 * @method static DatabaseInterface orWhereIsNull(string|RawQuery $column)
 * @method static DatabaseInterface andWhereIsNull(string|RawQuery $column)
 * @method static DatabaseInterface whereIsNotNull(string|RawQuery $column, string $logical = 'AND')
 * @method static DatabaseInterface orWhereIsNotNull(string|RawQuery $column)
 * @method static DatabaseInterface andWhereIsNotNull(string|RawQuery $column)
 * @method static DatabaseInterface like(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND')
 * @method static DatabaseInterface orLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static DatabaseInterface andLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static DatabaseInterface notLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND')
 * @method static DatabaseInterface orNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static DatabaseInterface andNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both')
 * @method static DatabaseInterface startLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface orStartLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface andStartLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface notStartLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface orStartNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface andStartNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface endLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface orEndLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface andEndLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface notEndLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface orEndNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static DatabaseInterface andEndNotLike(string|RawQuery|array $column, mixed $value = null)
 * @method static RawQuery subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true)
 * @method static DatabaseInterface group(Closure $closure, string $logical = 'AND')
 * @method static RawQuery raw(mixed $rawQuery)
 */
final class DB
{
    private static ?DatabaseInterface $database = null;

    /**
     * Prevent instantiation — this class is a pure static facade.
     */
    private function __construct()
    {
    }

    /**
     * Build a Database from the supplied connection (array or
     * {@see ConnectionInterface}) and store it as the shared facade target.
     *
     * "Immutable" here means: once set, the shared instance cannot be silently
     * replaced. A second call throws {@see DatabaseException} — callers that
     * need to swap connections should use {@see self::replaceImmutable()}
     * (explicit) or create a non-facade Database via {@see self::connect()}.
     *
     * @param array<string, mixed>|ConnectionInterface $connection
     *
     * @throws DatabaseException                When an immutable instance is
     *         already set.
     * @throws DatabaseInvalidArgumentException When $connection is invalid.
     */
    public static function createImmutable(array|ConnectionInterface $connection): DatabaseInterface
    {
        if (self::$database !== null) {
            throw new DatabaseException(
                'An immutable Database instance has already been set. '
                . 'Call DB::replaceImmutable() to swap it explicitly.'
            );
        }

        self::$database = self::connect($connection);

        return self::$database;
    }

    /**
     * Explicitly replace the shared facade target. Use when an application
     * truly needs to reset the connection (e.g. between test cases).
     *
     * Pass null to clear the facade entirely.
     *
     * @param array<string, mixed>|ConnectionInterface|DatabaseInterface|null $connection
     */
    public static function replaceImmutable(
        array|ConnectionInterface|DatabaseInterface|null $connection
    ): ?DatabaseInterface {
        if ($connection === null) {
            self::$database = null;

            return null;
        }

        self::$database = $connection instanceof DatabaseInterface
            ? $connection
            : self::connect($connection);

        return self::$database;
    }

    /**
     * Build a fresh, non-facade Database. The returned instance does not
     * touch the shared facade slot — useful for working with secondary
     * connections.
     *
     * @param array<string, mixed>|ConnectionInterface $connection
     *
     * @throws DatabaseInvalidArgumentException When $connection is invalid.
     */
    public static function connect(array|ConnectionInterface $connection): DatabaseInterface
    {
        return new Database($connection);
    }

    /**
     * The shared facade instance.
     *
     * @throws DatabaseException When no immutable instance has been set yet.
     */
    public static function getDatabase(): DatabaseInterface
    {
        if (self::$database === null) {
            throw new DatabaseException(
                'No immutable Database instance is configured. Call DB::createImmutable($connection) first.'
            );
        }

        return self::$database;
    }

    /**
     * @param array<int, mixed> $arguments
     *
     * @throws DatabaseException When no immutable instance is set, or when the
     *         method does not exist on the underlying Database / builder.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::getDatabase()->{$name}(...$arguments);
    }
}
