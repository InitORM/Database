# Raw queries

When the query builder doesn't cover your case (CTEs, vendor-specific syntax, EXPLAIN, recursive queries, …) drop down to raw SQL. There are two entry points:

- **`Database::query()`** — execute a complete prepared statement.
- **`DB::raw()` / `$db->raw()`** — wrap a SQL fragment for use inside the builder.

## Executing raw statements

`query()` returns the same `DataMapperInterface` you'd get from `read()`:

```php
$result = $db->query(
    'SELECT id, title FROM posts WHERE user_id = :id ORDER BY created_at DESC LIMIT 10',
    [':id' => 5]
);

foreach ($result->asAssoc()->rows() as $row) {
    echo $row['title'] . PHP_EOL;
}
```

Both `:name`-style and `?`-style placeholders work — the leading `:` on parameter keys is optional.

```php
$db->query('SELECT * FROM posts WHERE id = :id', ['id' => 7]);
// Both ":id" and "id" keys map to the :id placeholder.
```

For statements that don't return rows (`CREATE TABLE`, `INSERT`, …) the call still returns a DataMapper; ignore it or use `numRows()`:

```php
$inserted = $db->query(
    'INSERT INTO audit (event, payload) VALUES (:event, :payload)',
    [':event' => 'login', ':payload' => '{}']
)->numRows();
```

## Inlining SQL inside the builder

`DB::raw()` wraps a string in a `RawQuery` object — the builder treats these as opaque fragments and does not escape or parameterize them:

```php
DB::select(DB::raw("CONCAT(name, ' ', surname) AS fullname"))
    ->from('users')
    ->where(DB::raw('status = 1 OR status = 0'))
    ->limit(5)
    ->read();
```

`RawQuery` is also useful inside `set()`:

```php
DB::update('counters', [
    'value'      => DB::raw('value + 1'),
    'updated_at' => DB::raw('NOW()'),
], ['id' => 1]);
```

Generated SQL: `UPDATE counters SET value = value + 1, updated_at = NOW() WHERE id = :id`

## Mixing raw + bound

Bind explicit parameters by hand on raw fragments:

```php
$db->query(
    'WITH recent AS (
        SELECT id, score FROM posts WHERE created_at > :since
    )
    SELECT id FROM recent WHERE score > :min',
    [
        ':since' => '2025-01-01',
        ':min'   => 50,
    ]
);
```

## ⚠️ Safety

The fundamental rule:

> **Never embed unsanitized user input into a `RawQuery` or `query()` SQL string.**

`RawQuery` is *unescaped by definition* — its whole purpose is to bypass quoting. For any value derived from user input, parameterize it:

```php
// ❌ NEVER
$db->where(DB::raw("name = '$userInput'"));

// ✅ ALWAYS
$db->where('name', '=', $userInput);
```

For identifier-style values (column names, table names) that *must* be dynamic, validate against an allow-list before composing the SQL:

```php
$allowed = ['id', 'created_at', 'score'];
if (!in_array($sort, $allowed, true)) {
    throw new \InvalidArgumentException();
}
$db->orderBy($sort, 'DESC');
```

The builder will safely quote `$sort` as a column identifier, but it cannot tell whether `"id; DROP TABLE users;"` was supposed to be a column.

Continue with [Multiple connections](07-multiple-connections.md).
