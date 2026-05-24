# Getting started

This page walks through the smallest possible "hello, database" with `initorm/database` — install, connect, run one query.

## Install

```bash
composer require initorm/database
```

The package requires **PHP 8.1+** and `ext-pdo`. You also need the PDO driver for your database (`ext-pdo_mysql`, `ext-pdo_pgsql`, or `ext-pdo_sqlite`).

## Choose a usage style

There are two equally first-class ways to use this library:

- **Static facade (`DB::…`)** — one shared connection per application. Convenient for scripts, CLI tools, and applications that don't use DI containers.
- **Instance API (`new Database(…)`)** — explicit dependency injection. Recommended in larger applications, libraries, and anywhere you need more than one connection.

Pick whichever fits. See [Facade vs Instance](10-facade-vs-instance.md) for the trade-offs.

## First connection — facade

```php
<?php
require_once 'vendor/autoload.php';

use InitORM\Database\Facade\DB;

DB::createImmutable([
    'dsn'      => 'sqlite::memory:',
    'driver'   => 'sqlite',
    'charset'  => '',
]);

DB::query('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT)');

DB::create('notes', ['body' => 'Hello, InitORM!']);

$rows = DB::read('notes')->asAssoc()->rows();
print_r($rows);
// [ ['id' => '1', 'body' => 'Hello, InitORM!'] ]
```

## First connection — instance

```php
<?php
require_once 'vendor/autoload.php';

use InitORM\Database\Database;

$db = new Database([
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'charset'  => '',
]);

$db->query('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT)');
$db->create('notes', ['body' => 'Hello, InitORM!']);

$rows = $db->read('notes')->asAssoc()->rows();
```

## What you get

A `Database` instance composes:

- A **Connection** from `initorm/dbal` (PDO lifecycle, prepared statements, result mapping).
- A **QueryBuilder** from `initorm/query-builder` (fluent SQL assembly, parameter binding, dialect quoting).

The full surface of both is reachable through the Database — calls flow:

```
$db->select(...)->where(...)->read(...)
  └─ select/where forwarded to the inner builder
     read() compiles the builder's SQL and executes it through the connection
```

Continue with [Configuration](02-configuration.md) for the full credential reference.
