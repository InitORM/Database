# Logger and debug

The `log` credential lets you route per-query failure messages to disk, a callable, or any object with a `critical(string)` method — including PSR-3 loggers. `debug` controls whether bound parameters are included in those messages.

## File sink

The simplest form: pass a file path. The Logger writes via `file_put_contents()` with append mode.

```php
DB::createImmutable([
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log' => __DIR__ . '/var/log/db.log',
]);
```

The path may contain `{year}`, `{month}`, `{day}` placeholders — they're replaced at write time so daily-rotated logs work out of the box:

```php
'log' => __DIR__ . '/var/log/db-{year}-{month}-{day}.log',
// → var/log/db-2025-11-08.log
```

## Callable sink

Any callable that accepts a single string works:

```php
DB::createImmutable([
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log' => function (string $message): void {
        error_log($message);
    },
]);
```

`[$object, 'method']` arrays count as callables too:

```php
$monolog = new \Monolog\Logger('db');
$monolog->pushHandler(new \Monolog\Handler\StreamHandler('php://stderr'));

DB::createImmutable([
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log' => [$monolog, 'critical'],
]);
```

## Object sink (PSR-3 friendly)

Any object that exposes a `critical(string $message)` method is accepted. This is the contract `\Psr\Log\LoggerInterface` already satisfies, so you can pass a PSR-3 logger directly:

```php
DB::createImmutable([
    'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log' => $psr3Logger, // any PSR-3 LoggerInterface
]);
```

If your logger uses a different method name, wrap it in a closure:

```php
'log' => fn(string $msg) => $myLogger->error($msg),
```

## Debug mode

When `debug: true`, query failure messages are augmented with the bound parameters (JSON-encoded). The default failure message looks like this:

```
SQLSTATE[42S02]: Base table or view not found: ... no such table: users
SQL : SELECT * FROM users WHERE id = :id
```

With `debug: true`:

```
SQLSTATE[42S02]: Base table or view not found: ... no such table: users
SQL : SELECT * FROM users WHERE id = :id
PARAMS : {":id":5}
```

> Enable `debug` in development only. Parameter dumps will include user-supplied values, possibly including credentials and PII. Treat them as sensitive.

## Combining log + debug

The two settings compose:

```php
DB::createImmutable([
    'dsn'   => 'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'log'   => $psr3Logger,
    'debug' => true,
]);
```

…which gives you full failure messages (with params) routed through your PSR-3 pipeline.

## What gets logged

Only **query failures** flow into the sink. Successful queries do not produce log entries — that's what the [Query log profiler](09-query-log-profiler.md) is for. The two systems are independent.

Continue with [Query log profiler](09-query-log-profiler.md).
