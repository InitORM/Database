# Configuration

A Database is configured through a single credentials array, passed either to `DB::createImmutable()` or to `new Database(…)`. The keys are forwarded as-is to the underlying [`InitORM\DBAL\Connection\Connection`](https://github.com/InitORM/DBAL/blob/master/src/Connection/Connection.php).

## Full reference

| Key            | Type                                  | Default       | What it does                                                                                                                                                                                                                                |
| -------------- | ------------------------------------- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `dsn`          | `string`                              | _(built)_     | The PDO DSN. When empty, one is constructed from `driver`/`host`/`port`/`database`/`charset` via DBAL's `DsnBuilder`.                                                                                                                       |
| `driver`       | `string`                              | `'mysql'`     | `mysql`, `pgsql` / `postgres` / `postgresql`, `sqlite`, or any PDO driver name (`oci`, `sqlsrv`, …). Also picks the QueryBuilder dialect (identifier quoting).                                                                               |
| `host`         | `string`                              | `'127.0.0.1'` | Ignored when `dsn` is provided.                                                                                                                                                                                                             |
| `port`         | `int\|string`                         | `3306`        | Ignored when `dsn` is provided.                                                                                                                                                                                                             |
| `database`     | `string`                              | `''`          | For SQLite use `':memory:'` or a file path. Ignored when `dsn` is provided.                                                                                                                                                                 |
| `username`     | `string\|null`                        | `null`        |                                                                                                                                                                                                                                             |
| `password`     | `string\|null`                        | `null`        |                                                                                                                                                                                                                                             |
| `charset`      | `string`                              | `'utf8mb4'`   | MySQL only: applied via `SET NAMES`. Pass `''` to skip (SQLite, PostgreSQL).                                                                                                                                                                |
| `collation`    | `string\|null`                        | `null`        | MySQL only. Validated against `[A-Za-z0-9_]` before interpolation into `COLLATE`.                                                                                                                                                           |
| `options`      | `array<int, mixed>`                   | `[]`          | Merged on top of safe defaults: `ATTR_EMULATE_PREPARES=false`, `ATTR_PERSISTENT=false`, `ATTR_ERRMODE=ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE=FETCH_ASSOC`.                                                                            |
| `queryOptions` | `array<int, mixed>`                   | `[]`          | PDO `prepare()` options used for every prepared statement.                                                                                                                                                                                  |
| `log`          | `string\|callable\|object\|null`      | `null`        | See [Logger](08-logger-and-debug.md). Accepts a file path with `{year}/{month}/{day}` placeholders, a callable `function(string $msg)`, or any object with a `critical(string $msg)` method (PSR-3 LoggerInterface fits this contract).   |
| `debug`        | `bool`                                | `false`       | When true, query failure messages also include the bound parameters (JSON-encoded). **Enable in development only**.                                                                                                                         |
| `queryLogs`    | `bool`                                | `false`       | Bootstrap value for the query log buffer. You can flip this at runtime with `enableQueryLog()` / `disableQueryLog()`.                                                                                                                       |

## DSN: explicit vs auto-built

You can either supply a full DSN…

```php
new Database([
    'dsn'      => 'mysql:host=localhost;port=3306;dbname=app;charset=utf8mb4',
    'username' => 'app',
    'password' => 'secret',
]);
```

…or let DBAL assemble one from parts:

```php
new Database([
    'driver'   => 'mysql',
    'host'     => 'db.internal',
    'port'     => 3306,
    'database' => 'app',
    'charset'  => 'utf8mb4',
    'username' => 'app',
    'password' => 'secret',
]);
```

DBAL keys with `charset` only when the driver expects it (MySQL).

## Driver examples

### MySQL / MariaDB

```php
[
    'dsn'       => 'mysql:host=localhost;port=3306;dbname=app;charset=utf8mb4',
    'username'  => 'app',
    'password'  => '…',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]
```

### PostgreSQL

```php
[
    'dsn'      => 'pgsql:host=localhost;port=5432;dbname=app',
    'username' => 'app',
    'password' => '…',
    'driver'   => 'pgsql',
    'charset'  => '', // not applicable
]
```

### SQLite

```php
[
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/var/app.sqlite', // or ':memory:'
    'charset'  => '',
]
```

## Safe defaults you get for free

The default PDO options enable exception mode, disable emulated prepares (so prepared statements actually use server-side prepares on MySQL), keep connections non-persistent, and default the fetch mode to associative arrays:

```php
[
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]
```

Override any of them by passing your own `options` key — your values are merged on top, not replaced.

## Switching credentials after construction

Most setters on the underlying Connection (e.g. `setDatabase()`, `setHost()`) throw if PDO has already opened a connection — by design, since changing them silently would leak. Build a fresh Connection instead, or call `disconnect()` first.

Continue with [CRUD operations](03-crud.md).
