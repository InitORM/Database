# InitORM Database — documentation

Code-first guides for the `initorm/database` package. Each page is self-contained and includes runnable examples; tests in [`/tests`](../tests) double as living documentation.

| # | Page | Covers |
|---|---|---|
| 01 | [Getting started](01-getting-started.md) | install, first connection, first query |
| 02 | [Configuration](02-configuration.md) | credentials, DSN, charset/collation, PDO options |
| 03 | [CRUD operations](03-crud.md) | create / createBatch / read / update / updateBatch / delete + affectedRows |
| 04 | [Query builder surface](04-query-builder.md) | select, where, joins, group/order/limit, sub-queries |
| 05 | [Transactions](05-transactions.md) | commit, rollback, retries, testMode, exception handling |
| 06 | [Raw queries](06-raw-queries.md) | `query()`, `DB::raw()`, placeholder safety |
| 07 | [Multiple connections](07-multiple-connections.md) | `connect()` vs `createImmutable()`, sibling builders |
| 08 | [Logger and debug](08-logger-and-debug.md) | file / callable / object sinks, debug-mode payloads |
| 09 | [Query log profiler](09-query-log-profiler.md) | enable/disable, log shape, perf analysis |
| 10 | [Facade vs instance](10-facade-vs-instance.md) | when to use `DB::…`, when to inject `DatabaseInterface` |
| 11 | [Architecture](11-architecture.md) | the `__call` chain, dependency map, per-layer responsibilities |
| 12 | [Upgrade guide v2 → v3](12-upgrade-guide.md) | breaking changes and the migration script |

## Related package docs

- [`initorm/dbal`](https://github.com/InitORM/DBAL/tree/master/docs) — connection lifecycle, DataMapper API.
- [`initorm/query-builder`](https://github.com/InitORM/QueryBuilder) — the full SQL builder surface this package mixes in.
