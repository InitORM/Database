# InitORM Database

Composes [`initorm/dbal`](https://github.com/InitORM/DBAL) (PDO connection + result mapper) and [`initorm/query-builder`](https://github.com/InitORM/QueryBuilder) (fluent SQL builder) into a single Database manager with CRUD helpers, transactions, query logging, and an optional static facade.

[![Latest Stable Version](http://poser.pugx.org/initorm/database/v)](https://packagist.org/packages/initorm/database)
[![Total Downloads](http://poser.pugx.org/initorm/database/downloads)](https://packagist.org/packages/initorm/database)
[![License](http://poser.pugx.org/initorm/database/license)](https://packagist.org/packages/initorm/database)
[![PHP Version Require](http://poser.pugx.org/initorm/database/require/php)](https://packagist.org/packages/initorm/database)
[![PHPUnit](https://github.com/InitORM/Database/actions/workflows/phpunit.yml/badge.svg)](https://github.com/InitORM/Database/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/InitORM/Database/actions/workflows/phpstan.yml/badge.svg)](https://github.com/InitORM/Database/actions/workflows/phpstan.yml)
[![PHP_CodeSniffer](https://github.com/InitORM/Database/actions/workflows/phpcs.yml/badge.svg)](https://github.com/InitORM/Database/actions/workflows/phpcs.yml)

---

## Requirements

- **PHP 8.1 or later**
- `ext-pdo`
- One of `ext-pdo_mysql`, `ext-pdo_pgsql`, or `ext-pdo_sqlite` depending on the database you target.

## Supported databases

Any database with a PDO driver that follows standard SQL works out of the box. The query builder ships dialect-aware identifier quoting for **MySQL/MariaDB**, **PostgreSQL**, and **SQLite**; for everything else (`oci`, `sqlsrv`, …) it falls back to a generic, no-escaping driver.

## Installation

```bash
composer require initorm/database
```

---

## Quick start

```php
<?php
require_once 'vendor/autoload.php';

use InitORM\Database\Facade\DB;

DB::createImmutable([
    'dsn'       => 'mysql:host=localhost;port=3306;dbname=app;charset=utf8mb4',
    'username'  => 'app',
    'password'  => 'secret',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// CRUD shortcut
DB::create('users', ['name' => 'Alice', 'email' => 'alice@example.com']);

// Fluent builder + CRUD
$rows = DB::select('id', 'name')
    ->where('active', '=', 1)
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->read('users')
    ->asAssoc()
    ->rows();
```

> `createImmutable()` sets the application-wide facade once. Calling it a second time throws — see [`docs/10-facade-vs-instance.md`](docs/10-facade-vs-instance.md) for swap and multi-connection patterns.

---

## Configuration reference

All keys are passed through to the underlying [`InitORM\DBAL\Connection\Connection`](https://github.com/InitORM/DBAL/blob/master/src/Connection/Connection.php) constructor. Defaults are sane for MySQL.

| Key            | Type                                 | Default       | Notes                                                                                          |
| -------------- | ------------------------------------ | ------------- | ---------------------------------------------------------------------------------------------- |
| `dsn`          | `string`                             | _(built)_     | When empty, a DSN is constructed from `driver`, `host`, `port`, `database`, `charset`.         |
| `driver`       | `string`                             | `'mysql'`     | `mysql`, `pgsql`/`postgres`/`postgresql`, `sqlite`, or any PDO driver name.                    |
| `host`         | `string`                             | `'127.0.0.1'` | Ignored when `dsn` is set explicitly.                                                          |
| `port`         | `int\|string`                        | `3306`        | Ignored when `dsn` is set explicitly.                                                          |
| `database`     | `string`                             | `''`          | For SQLite use `':memory:'` or a file path.                                                    |
| `username`     | `string\|null`                       | `null`        |                                                                                                |
| `password`     | `string\|null`                       | `null`        |                                                                                                |
| `charset`      | `string`                             | `'utf8mb4'`   | Applied on MySQL via `SET NAMES`. Pass `''` to skip (e.g. SQLite).                             |
| `collation`    | `string\|null`                       | `null`        | MySQL-only. Validated against `[A-Za-z0-9_]` before interpolation.                             |
| `options`      | `array<int, mixed>`                  | `[]`          | Merged on top of safe PDO defaults (exceptions on errors, FETCH_ASSOC, no emulation).          |
| `queryOptions` | `array<int, mixed>`                  | `[]`          | PDO `prepare()` options used for every statement.                                              |
| `log`          | `string\|callable\|object\|null`     | `null`        | See [Logger](#logger). File path, callable, or any object with a `critical(string)` method.    |
| `debug`        | `bool`                               | `false`       | When true, query failure messages also include the bound parameters (JSON-encoded).            |
| `queryLogs`    | `bool`                               | `false`       | Bootstrap value for the query log buffer (see [Query log](#query-log)).                        |

---

## CRUD

All CRUD helpers reset the builder's state on completion, so the next call starts with a clean slate. Every helper returns `bool true` on successful execution and throws on failure — use [`affectedRows()`](#affected-rows) when you also need to know how many rows changed.

### Create

```php
use InitORM\Database\Facade\DB;

DB::create('posts', [
    'title'   => 'Post Title',
    'content' => 'Post Content',
]);

$newId = DB::insertId();
```

Generated SQL: `INSERT INTO posts (title, content) VALUES (:title, :content)`

### Create batch

```php
DB::createBatch('posts', [
    ['title' => 'Post #1', 'content' => 'Body 1', 'author_id' => 5],
    ['title' => 'Post #2', 'content' => 'Body 2'],
]);
```

Generated SQL: `INSERT INTO posts (title, content, author_id) VALUES (:title, :content, :author_id), (:title_1, :content_1, NULL)`

Missing columns in any row compile to `NULL`.

### Read

```php
$res = DB::select('user.name AS author_name', 'post.id', 'post.title')
    ->from('post')
    ->selfJoin('user', 'user.id=post.author')
    ->where('post.status', '=', 1)
    ->orderBy('post.id', 'ASC')
    ->orderBy('post.created_at', 'DESC')
    ->offset(20)
    ->limit(10)
    ->read();

foreach ($res->asAssoc()->rows() as $row) {
    echo $row['title'] . ' by ' . $row['author_name'] . PHP_EOL;
}
```

### Update

```php
DB::update('post', ['title' => 'New Title', 'content' => 'New Content'], ['id' => 13]);
```

Generated SQL: `UPDATE post SET title = :title, content = :content WHERE id = :id`

### Update batch

```php
DB::where('status', '!=', 0)
    ->updateBatch('id', 'post', [
        ['id' => 5,  'title' => 'New Title #5',  'content' => 'New Content #5'],
        ['id' => 10, 'title' => 'New Title #10'],
    ]);
```

Generated SQL (formatted):

```sql
UPDATE post SET
    title = CASE WHEN id = :id THEN :title WHEN id = :id_1 THEN :title_1 ELSE title END,
    content = CASE WHEN id = :id_2 THEN :content ELSE content END
WHERE status != :status AND id IN (:id_3, :id_4)
```

### Delete

```php
DB::delete('post', ['id' => 13]);
```

Generated SQL: `DELETE FROM post WHERE id = :id`

### Affected rows

```php
DB::update('users', ['active' => 0], ['active' => 1]);
echo DB::affectedRows(); // e.g. 42
```

`affectedRows()` returns the row count of the most recent CRUD call on the same Database instance.

---

## Raw queries

```php
$res = DB::query(
    'SELECT id, title FROM post WHERE user_id = :id',
    [':id' => 5]
);

if ($res->numRows() > 0) {
    $result = $res->asObject()->row();
    echo $result->title;
}
```

You can also use `DB::raw()` inside the builder to inject literal SQL fragments — **never embed unsanitized user input**:

```php
$res = DB::select(DB::raw("CONCAT(name, ' ', surname) AS fullname"))
    ->where(DB::raw('status = 1 OR status = 0'))
    ->limit(5)
    ->read('users');
```

---

## Transactions

```php
DB::transaction(function (\InitORM\Database\Interfaces\DatabaseInterface $db) {
    $db->create('orders',     ['user_id' => 5, 'total' => 199.90]);
    $db->create('order_items',['order_id' => $db->insertId(), 'sku' => 'X', 'qty' => 1]);
});
```

- The closure receives the Database instance.
- Throw to abort: the current transaction is rolled back; if `$attempt > 1` the closure is retried; otherwise the original error is rethrown wrapped in a `DatabaseException` (the original is reachable via `$e->getPrevious()`).
- Pass `testMode: true` to roll back even on success — useful for integration tests.

```php
$caught = null;
try {
    DB::transaction(function ($db) {
        $db->create('orders', [...]);
        throw new \RuntimeException('boom');
    });
} catch (\InitORM\Database\Exceptions\DatabaseException $e) {
    $caught = $e->getPrevious(); // \RuntimeException 'boom'
}
```

---

## Multiple connections

`DB::createImmutable()` registers a single shared facade. For secondary connections, use `DB::connect()` or instantiate `Database` directly — these do not touch the facade slot.

```php
use InitORM\Database\Database;

$reports = new Database([
    'dsn'      => 'pgsql:host=reports.internal;dbname=reports',
    'username' => 'reports_ro',
    'password' => '…',
    'driver'   => 'pgsql',
]);

$reports->read('events')->asAssoc()->rows();
```

If you must swap the immutable facade target (rare; mostly for tests), call `DB::replaceImmutable($next)` explicitly — silent overrides are forbidden.

---

## Developer tools

### Logger

The `log` credential accepts three shapes — a file path, a callable, or any object with a `critical(string)` method. The DBAL Logger writes a single string message per failed query, prefixed with the SQL and (when `debug` is on) the bound parameters.

```php
// 1) File path — file_put_contents() with append
DB::createImmutable([
    'dsn'  => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log'  => __DIR__ . '/var/log/db-{year}-{month}-{day}.log',
]);

// 2) Callable
DB::createImmutable([
    'dsn'  => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log'  => function (string $msg): void {
        error_log($msg);
    },
]);

// 3) Object with critical() (or a [$obj, 'method'] callable)
class Logger {
    public function critical(string $msg): void { /* … */ }
}

DB::createImmutable([
    'dsn'  => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log'  => new Logger(),
]);
```

### Debug mode

```php
DB::createImmutable([
    'dsn'   => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'debug' => true, // include bound parameters in failure messages
]);
```

> Enable in development only — bound parameter dumps can include credentials and PII.

### Query log

```php
DB::enableQueryLog();
DB::read('users', ['id', 'name'], ['active' => 1]);

var_dump(DB::getQueryLogs());
/*
[
    [
        'query' => 'SELECT id, name FROM users WHERE active = :active',
        'args'  => [':active' => 1],
        'timer' => 0.000642,
    ],
]
*/
```

`enableQueryLog()` / `disableQueryLog()` return the Database instance for chaining; `getQueryLogs()` returns every recorded entry. The buffer lives on the Connection — calling `disableQueryLog()` stops recording but does not clear previously-collected entries.

---

## Documentation

In-depth, code-first guides live under [`docs/`](docs/):

- [`01-getting-started.md`](docs/01-getting-started.md)
- [`02-configuration.md`](docs/02-configuration.md)
- [`03-crud.md`](docs/03-crud.md)
- [`04-query-builder.md`](docs/04-query-builder.md)
- [`05-transactions.md`](docs/05-transactions.md)
- [`06-raw-queries.md`](docs/06-raw-queries.md)
- [`07-multiple-connections.md`](docs/07-multiple-connections.md)
- [`08-logger-and-debug.md`](docs/08-logger-and-debug.md)
- [`09-query-log-profiler.md`](docs/09-query-log-profiler.md)
- [`10-facade-vs-instance.md`](docs/10-facade-vs-instance.md)
- [`11-architecture.md`](docs/11-architecture.md)
- [`12-upgrade-guide.md`](docs/12-upgrade-guide.md) — **migrating from v2 to v3**

---

## Contributing

Contributions are welcome. The general flow is:

1. Fork and branch off `master`.
2. Add tests for the behaviour you change — see [`tests/`](tests/) for patterns (SQLite in-memory, fast and dependency-free).
3. Run the full quality suite locally:
   ```bash
   composer qa   # phpcs + phpstan + phpunit
   ```
4. Open a PR — CI will run the same suite across PHP 8.1–8.4.

By submitting a contribution you agree to license it under the MIT License.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) — `<info@muhammetsafak.com.tr>`

## License

Released under the [MIT License](./LICENSE).
