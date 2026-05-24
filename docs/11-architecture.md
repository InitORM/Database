# Architecture

`initorm/database` is a thin glue layer over two independent packages. Understanding the dependency direction and the `__call` chain makes most "where does this method live?" questions answer themselves.

## Package layout

```
┌─────────────────────────────────────────────────────────┐
│ initorm/database  ←  CRUD + transaction + facade        │
│   ├─ Database (concrete)                                │
│   ├─ DatabaseInterface (@mixin QueryBuilderInterface)   │
│   └─ Facade\DB (static)                                 │
└───────┬──────────────────────────┬──────────────────────┘
        │                          │
        ▼                          ▼
┌─────────────────────┐  ┌─────────────────────────────────┐
│ initorm/dbal        │  │ initorm/query-builder           │
│  Connection (PDO)   │  │  QueryBuilder (SQL assembly)    │
│  DataMapper (rows)  │  │  Compilers (string-join)        │
└─────────────────────┘  └─────────────────────────────────┘
```

- `initorm/dbal` and `initorm/query-builder` are **independent** — they have no knowledge of each other.
- `initorm/database` is the only place where they're stitched together.

## Inside a Database

```
┌────────────────────────────────────────────────────────┐
│ Database                                               │
│  ├─ ConnectionInterface  $connection                   │  ← from DBAL
│  ├─ QueryBuilderInterface $builder                     │  ← from QueryBuilder
│  ├─ QueryBuilderFactoryInterface $queryBuilderFactory  │
│  └─ ?DataMapperInterface $lastResult                   │  ← for affectedRows()
└────────────────────────────────────────────────────────┘
```

The constructor accepts either a credentials array (which it forwards to `Connection::__construct`) or an already-built `ConnectionInterface`. It picks the QueryBuilder driver from `$connection->getDriver()` so identifier quoting is dialect-correct out of the box.

## The `__call` chain

Method calls walk down through three layers, with each layer re-wrapping the chain so callers always end up holding the outer object:

```
caller  →  Database::__call(...)  →  QueryBuilder::method(...)
                │
                ├─ if the builder returned $this → return $database (re-wrap)
                └─ otherwise                       → return the value as-is
```

Concrete example:

```php
$db->select('id')->where('id', '=', 1)->read('users');
```

1. `select('id')` doesn't exist on Database → `__call` forwards to `$this->builder->select('id')`. The builder returns itself → re-wrapped to `$db`.
2. `where('id', '=', 1)` → same path; returns `$db`.
3. `read('users')` exists on Database directly → compiles SQL, calls `$this->connection->query(...)`, resets builder state, returns the `DataMapperInterface`.

The same pattern repeats one layer deeper inside DBAL: `Connection::__call` forwards unknowns to PDO, and `DataMapper::__call` forwards to `PDOStatement`. The whole stack is fluent end-to-end.

## State management

There are two pieces of state inside the Database:

1. **The builder's structure** — `select`, `from`, `where`, `set`, … buckets. Mutated by builder calls; **reset in a `finally` block after every CRUD execution** so the next call starts clean. This was a bug in earlier versions; see [the v2→v3 upgrade guide](12-upgrade-guide.md).
2. **The builder's parameter bag** — placeholder → value map. Reset in the same `finally` block.

There is no Database-level mutable state beyond `$lastResult` (used by `affectedRows()`), which makes the class safe to use across nested calls inside the same script.

## What is *not* shared with siblings

`withFreshBuilder()` returns a Database that:

- **Shares** the same `ConnectionInterface` (and therefore the same PDO, same transaction context).
- **Does not share** builder state — a brand-new QueryBuilder is constructed.

This is the right shape for sub-queries: same live connection, independent SQL assembly.

```php
$base = new Database([...]);
$sub  = $base->withFreshBuilder();

self::assertSame($base->getConnection(), $sub->getConnection()); // ✅
self::assertNotSame($base, $sub);                                // ✅
```

## What is *not* in this package

A common point of confusion: a lot of the surface you'll use lives in *other* packages.

| You're looking for                       | Look in                  |
|------------------------------------------|--------------------------|
| `setHost`, `setPassword`, `getCharset`   | `initorm/dbal` Connection|
| `asAssoc`, `row`, `rows`, `numRows`      | `initorm/dbal` DataMapper|
| `select`, `where`, `join`, `groupBy`, …  | `initorm/query-builder`  |
| Dialect quoting / `RawQuery`             | `initorm/query-builder`  |
| Active-record-style models, hooks        | `initorm/orm` (separate) |

`initorm/database` itself is small — under 400 lines of code — because almost everything routes through `__call` to one of the layers below.

Continue with [Upgrade guide v2 → v3](12-upgrade-guide.md).
