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
namespace InitORM\Database\Facade;

use PDO;
use Closure;
use InitORM\Database\Database;
use InitORM\DBAL\DataMapper\Interfaces\DataMapperInterface;
use \InitORM\QueryBuilder\{ParameterInterface, RawQuery};
use InitORM\Database\Exceptions\DatabaseException;
use InitORM\Database\Interfaces\DatabaseInterface;
use InitORM\DBAL\Connection\Interfaces\ConnectionInterface;

/**
 * @mixin DatabaseInterface
 * @method static PDO getPDO()
 * @method static DatabaseInterface enableQueryLog()
 * @method static DatabaseInterface disableQueryLog()
 * @method static array getQueryLogs()
 * @method static ConnectionInterface getConnection()
 * @method static DatabaseInterface builder()
 * @method static DataMapperInterface query(string $sqlQuery, ?array $parameters = null, ?array $options = null)
 * @method static int|string insertId()
 * @method static bool transaction(Closure $closure, int $attempt = 1, bool $testMode = false)
 * @method static bool create(?string $table = null, ?array $set = null)
 * @method static bool createBatch(?string $table = null, ?array $set = null)
 * @method static DataMapperInterface read(?string $table = null, ?array $selectors = null, ?array $conditions = null)
 * @method static bool update(?string $table = null, ?array $set = null, ?array $conditions = null)
 * @method static bool updateBatch(string $referenceColumn, ?string $table = null, ?array $set = null, ?array $conditions = null)
 * @method static bool delete(?string $table, ?array $conditions = null)
 * @method static ParameterInterface getParameter()
 * @method static DatabaseInterface setParameter(string $key, mixed $value)
 * @method static DatabaseInterface setParameters(array $parameters = [])
 * @method static DatabaseInterface select(string|RawQuery|string[]|RawQuery[] ...$columns)
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
 * @method static DatabaseInterface selectSum(string|RawQuery $column, ?string $alias = null)
 * @method static DatabaseInterface selectConcat(array $columns, ?string $alias = null)
 * @method static DatabaseInterface from(RawQuery|string $table, ?string $alias = null)
 * @method static DatabaseInterface addFrom(RawQuery|string $table, ?string $alias = null)
 * @method static DatabaseInterface table(string|RawQuery $table)
 * @method static DatabaseInterface groupBy(string|RawQuery|array ...$columns)
 * @method static DatabaseInterface join(RawQuery|string $table, RawQuery|string|Closure $onStmt = null, string $type = 'INNER')
 * @method static DatabaseInterface selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface naturalJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt)
 * @method static DatabaseInterface orderBy(RawQuery|string $column, string $soft = 'ASC')
 * @method static DatabaseInterface where(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface having(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface on(RawQuery|string $column, string $operator = '=', mixed $value = null, string $logical = 'AND')
 * @method static DatabaseInterface set(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static DatabaseInterface addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true)
 * @method static DatabaseInterface andWhere(string|RawQuery $column, string $operator = '=', mixed $value = null)
 * @method static DatabaseInterface orWhere(string|RawQuery $column, string $operator = '=', mixed $value = null)
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
 * @method static DatabaseInterface offset(int $offset = 0)
 * @method static DatabaseInterface limit(int $limit)
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
 * @method static DatabaseInterface group(Closure $closure)
 * @method static RawQuery raw(mixed $rawQuery)
 */
final class DB
{

    private static DatabaseInterface $database;

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws DatabaseException
     */
    public function __call($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws DatabaseException
     */
    public static function __callStatic($name, $arguments)
    {
        return self::getDatabase()->{$name}(...$arguments);
    }

    /**
     * @param array|ConnectionInterface $connection
     * @return void
     */
    public static function createImmutable($connection): void
    {
        self::$database = self::connect($connection);
    }

    /**
     * @param array|ConnectionInterface $connection
     * @return DatabaseInterface
     */
    public static function connect($connection): DatabaseInterface
    {
        return new Database($connection);
    }

    /**
     * @return DatabaseInterface
     * @throws DatabaseException
     */
    public static function getDatabase(): DatabaseInterface
    {
        if (!isset(self::$database)) {
            throw new DatabaseException('To create an immutable, first use the "createImmutable()" method.');
        }

        return self::$database;
    }

}
