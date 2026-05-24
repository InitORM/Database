# CRUD operations

The Database surface exposes five CRUD helpers plus their batch variants. They share a few common rules:

- **The builder is the source of truth.** Anything you chain (`where()`, `select()`, `orderBy()`, …) is folded into the compiled SQL.
- **State resets after every CRUD call.** The structure and parameter bag are wiped in a `finally` block, so the next call starts clean — even if execution threw.
- **Return value is `bool true` on success.** Failures throw (`SQLExecuteException`, `ConnectionException`, `QueryBuilderException`). For affected-row counts, use [`affectedRows()`](#affected-rows).
- **Parameters are auto-bound.** You never concatenate user values into SQL; the builder registers them as `:placeholder` and binds via PDO.

All examples below assume the SQLite-in-memory setup from [Getting started](01-getting-started.md).

## Create

```php
DB::create('users', [
    'name'   => 'Alice',
    'email'  => 'alice@example.com',
    'active' => 1,
]);

// Auto-incremented PK
echo DB::insertId(); // "1"
```

Generated SQL: `INSERT INTO users (name, email, active) VALUES (:name, :email, :active)`

`create()` accepts either:

```php
DB::create('users', $data);                  // table + set
DB::from('users')->set($data)->create();     // builder-first
```

Both produce identical SQL.

## Create batch

```php
DB::createBatch('users', [
    ['name' => 'B', 'email' => 'b@example.com', 'active' => 1],
    ['name' => 'C', 'email' => 'c@example.com', 'active' => 0],
    ['name' => 'D', 'email' => 'd@example.com'], // missing 'active' → NULL
]);
```

Generated SQL (one statement, multi-row):

```sql
INSERT INTO users (name, email, active) VALUES
    (:name, :email, :active),
    (:name_1, :email_1, :active_1),
    (:name_2, :email_2, NULL)
```

Missing columns in any row compile to the literal `NULL` — the column union is collected across all rows.

## Read

```php
$result = DB::read('users', ['id', 'name', 'email'], ['active' => 1]);

foreach ($result->asAssoc()->rows() as $row) {
    printf("#%d %s <%s>\n", $row['id'], $row['name'], $row['email']);
}
```

Generated SQL: `SELECT id, name, email FROM users WHERE active = :active`

`read()` returns a `DataMapperInterface`. The most useful methods on it:

- `asAssoc() / asObject() / asClass(StdClass::class) / asLazy()` — fetch mode
- `row()` — fetch the next row (`array|object|null`)
- `rows()` — fetch all remaining rows
- `numRows()` — `rowCount()` of the underlying statement

Mix the builder freely:

```php
DB::select('id', 'name')
    ->from('users')
    ->whereIn('id', [1, 2, 3])
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->read()
    ->asAssoc()
    ->rows();
```

## Update

```php
DB::update('users', ['active' => 0], ['id' => 1]);
```

Generated SQL: `UPDATE users SET active = :active WHERE id = :id`

Or builder-first:

```php
DB::where('email', 'LIKE', '%@example.com')
    ->update('users', ['active' => 0]);
```

## Update batch (CASE/WHEN per row)

```php
DB::where('status', '!=', 0)
    ->updateBatch('id', 'users', [
        ['id' => 1, 'score' => 100],
        ['id' => 2, 'score' => 200],
    ]);
```

Generated SQL (formatted):

```sql
UPDATE users SET
    score = CASE
        WHEN id = :id   THEN :score
        WHEN id = :id_1 THEN :score_1
        ELSE score
    END
WHERE status != :status AND id IN (:id_2, :id_3)
```

The first argument is the **reference column** — every row in `$set` must contain it. The compiler folds the row IDs into an automatic `WHERE … IN (…)` clause so unrelated rows aren't touched.

## Delete

```php
DB::delete('users', ['id' => 2]);
```

Generated SQL: `DELETE FROM users WHERE id = :id`

A delete without `WHERE` is allowed but discouraged; the compiler falls back to `WHERE 1` for clarity:

```php
DB::delete('users');
// DELETE FROM users WHERE 1
```

## Affected rows

```php
DB::update('users', ['active' => 0], ['active' => 1]);
echo DB::affectedRows(); // e.g. 42
```

`affectedRows()` returns the row count of the most recent CRUD call on the same Database. It's `0` when no CRUD call has executed yet and reliable for INSERT/UPDATE/DELETE on common drivers. For SELECT it depends on whether the driver buffers results — use `numRows()` on the returned DataMapper when you need a hard guarantee.

## Conditions shortcut shape

The `$conditions` parameter on `read` / `update` / `updateBatch` / `delete` accepts a mixed-key array:

| Key type | Effect                                                                   |
| -------- | ------------------------------------------------------------------------ |
| String   | `where(key, '=', value)` — equality comparison                           |
| Integer  | `where(value)` — `value` is a `RawQuery` or a literal column expression  |

```php
// String keys
DB::read('users', null, ['active' => 1, 'role' => 'admin']);
// WHERE active = :active AND role = :role

// Integer key with RawQuery
DB::read('users', null, [DB::raw('score > 50')]);
// WHERE score > 50
```

Continue with [the query builder surface](04-query-builder.md).
