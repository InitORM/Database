# Query builder surface

Every method on `InitORM\QueryBuilder\QueryBuilderInterface` is reachable directly through a Database instance via `__call`. Calls that return the builder are re-wrapped to return the Database, so fluent chains span the wrapper boundary:

```php
$db->select(...)->where(...)->orderBy(...)->read('users');
// ^ all of those return $db (not the builder), so .read() works.
```

This page is a quick tour of the surface. For the authoritative reference, see the [QueryBuilder package docs](https://github.com/InitORM/QueryBuilder).

## SELECT projection

```php
$db->select('id', 'name', $db->raw('NOW() AS now'));
$db->selectCount('id', 'total');
$db->selectMax('score', 'max_score');
$db->selectAs('name', 'username');
$db->selectConcat(['first_name', $db->raw("' '"), 'last_name'], 'full_name');
```

Helpers exist for `Count`, `CountDistinct`, `Max`, `Min`, `Avg`, `Sum`, `Upper`, `Lower`, `Length`, `Mid`, `Left`, `Right`, `Distinct`, `Coalesce`, `As`, `Concat`.

## FROM / table

```php
$db->from('users');                 // FROM users
$db->from('users', 'u');            // FROM users AS u
$db->addFrom('roles', 'r');         // FROM users AS u, roles AS r
$db->table('users');                // FROM users (alias-less)
```

`from()` resets the table list; `addFrom()` appends.

## JOIN

```php
$db->join('roles', 'roles.user_id = users.id', 'LEFT');
$db->innerJoin('roles', 'roles.user_id = users.id');
$db->leftJoin('roles', 'roles.user_id = users.id');
$db->rightJoin('roles', 'roles.user_id = users.id');
$db->leftOuterJoin('roles', 'roles.user_id = users.id');
$db->rightOuterJoin('roles', 'roles.user_id = users.id');
$db->selfJoin('roles', 'roles.user_id = users.id'); // comma-FROM with ON folded into WHERE
$db->naturalJoin('roles');                          // no ON clause
```

The `$onStmt` argument can be a string, a `RawQuery`, or a `Closure` that receives a fresh builder for composing complex ON expressions.

## WHERE / HAVING / ON

The basic shape is `where(column, operator, value, logical = 'AND')`:

```php
$db->where('id', '=', 5);
$db->where('age', '>', 18);
$db->where('email', 'LIKE', '%@example.com');

// 2-arg shortcut: operator defaults to '='
$db->where('id', 5);

// 1-arg form: column is a RawQuery / literal expression
$db->where($db->raw('score > 50'));

// Logical operator
$db->where('a', '=', 1)->where('b', '=', 2, 'OR');
$db->andWhere('c', '=', 3);
$db->orWhere('d', '=', 4);
```

### IS NULL / IS NOT NULL

```php
$db->whereIsNull('deleted_at');
$db->whereIsNotNull('email');
$db->andWhereIsNull('locked_at');
$db->orWhereIsNotNull('verified_at');
```

### IN / NOT IN

```php
$db->whereIn('id', [1, 2, 3]);
$db->whereNotIn('id', [4, 5]);
$db->orWhereIn('role', ['admin', 'editor']);
```

Numeric elements are inlined verbatim; strings are parameterized. A `RawQuery` (sub-query) is rendered as-is.

### BETWEEN / NOT BETWEEN

```php
$db->between('age', 18, 65);
$db->between('age', [18, 65]);  // also accepted
$db->notBetween('score', 0, 50);
```

### LIKE family

```php
$db->like('name', 'john', 'both');    // LIKE '%john%'
$db->like('name', 'john', 'start');   // LIKE 'john%'
$db->like('name', 'john', 'end');     // LIKE '%john'

$db->startLike('email', 'admin');     // LIKE 'admin%'
$db->endLike('email', '.com');        // LIKE '%.com'
$db->notLike('name', 'spam');         // NOT LIKE '%spam%'
```

All forms have `or*` / `and*` siblings (`orLike`, `andStartLike`, …).

### Sub-queries and groups

```php
$db->whereIn('user_id', $db->subQuery(function ($qb) {
    $qb->select('id')->from('users')->where('active', '=', 1);
}));
// WHERE user_id IN (SELECT id FROM users WHERE active = :active)

$db->group(function ($qb) {
    $qb->where('a', '=', 1)->orWhere('b', '=', 2);
});
// WHERE (a = :a OR b = :b)
```

## GROUP BY / HAVING / ORDER BY / LIMIT / OFFSET

```php
$db->select('role', $db->raw('COUNT(*) AS n'))
    ->from('users')
    ->groupBy('role')
    ->having('n', '>', 10)
    ->orderBy('n', 'DESC')
    ->limit(5)
    ->offset(10)
    ->read();
```

## INSERT / UPDATE shape (`set()`)

```php
$db->from('users')->set(['name' => 'Eve', 'active' => 1])->create();
$db->from('users')->set('name', 'Eve')->set('active', 1)->create();
```

Both forms produce the same INSERT. Repeated `set([...])` calls on `createBatch()` / `updateBatch()` produce multi-row shapes.

## Inspecting the compiled SQL

```php
$db->select('id')->from('users')->where('active', '=', 1);

// Generate SQL without executing:
$sql        = $db->getParameter()->all()
              + ['__sql' => $db->generateSelectQuery()];

// Or call directly through the chain (Database forwards to the builder)
echo $db->generateSelectQuery();
// SELECT id FROM users WHERE active = :active
print_r($db->getParameter()->all());
// [':active' => 1]
```

For more, the full set of compile methods (`generateInsertQuery`, `generateBatchInsertQuery`, `generateUpdateQuery`, `generateUpdateBatchQuery`, `generateDeleteQuery`) is available through `__call`.

Continue with [Transactions](05-transactions.md).
