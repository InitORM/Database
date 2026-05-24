# Facade vs instance

`initorm/database` ships both a static facade (`InitORM\Database\Facade\DB`) and a pure instance API (`new Database(...)` returning `DatabaseInterface`). Both call into the same underlying machinery — pick based on what fits your application.

## Quick comparison

| Concern                          | Facade `DB::…`                          | Instance API                              |
|----------------------------------|------------------------------------------|-------------------------------------------|
| Setup                            | `DB::createImmutable($cfg)` once         | `new Database($cfg)`                      |
| Type-hint                        | n/a (static)                             | `DatabaseInterface`                       |
| Dependency injection             | Implicit, global state                   | Explicit, container-friendly              |
| Multiple connections             | Awkward — needs `connect()` for extras   | Natural — instantiate as many as needed   |
| Testability                      | Use `DB::replaceImmutable($mock)`        | Inject a mock through the constructor     |
| Best for                         | Scripts, CLIs, smaller apps              | Libraries, services, large apps with DI   |

## When the facade is right

- The application has **one logical database** and reads it from many places.
- You don't have a DI container, or threading a Database around feels like overkill.
- You want short, readable call sites: `DB::read('users')`.

```php
use InitORM\Database\Facade\DB;

DB::createImmutable($cfg);

// Anywhere in the codebase:
$users = DB::read('users')->asAssoc()->rows();
```

## When the instance API is right

- The application has **more than one database** (replicas, tenant DBs, reporting warehouse, …).
- You inject dependencies through a container or constructor.
- You write a library and don't want to force a global on your callers.
- You want concrete types in IDE-friendly type hints.

```php
use InitORM\Database\Interfaces\DatabaseInterface;

final class UserRepository
{
    public function __construct(private DatabaseInterface $db) {}
}
```

## You can use both at once

The facade and the instance API don't conflict. `DB::createImmutable()` registers a single shared instance; everything you build through `new Database(...)` or `DB::connect()` lives outside that slot.

```php
DB::createImmutable($primaryCfg);              // facade points at primary
$replica = DB::connect($replicaCfg);           // a separate, non-facade instance

DB::read('users');                             // → primary
$replica->read('users');                       // → replica
```

## Facade and testability

Two patterns work well in tests:

**1. Inject a real Database into a fresh test-only facade slot.**

```php
protected function setUp(): void
{
    parent::setUp();
    DB::replaceImmutable(new Database([
        'driver' => 'sqlite', 'database' => ':memory:', 'charset' => '',
    ]));
    DB::query('CREATE TABLE …');
}

protected function tearDown(): void
{
    DB::replaceImmutable(null); // clear the slot for the next test
    parent::tearDown();
}
```

**2. Skip the facade in test code; instantiate `Database` directly.**

This is exactly what the package's own tests do — see [`tests/AbstractDatabaseTestCase.php`](../tests/AbstractDatabaseTestCase.php).

## Why "immutable"?

`createImmutable()` only succeeds once. The previous behaviour (silent override) made code like this hard to reason about:

```php
// In bootstrap.php:
DB::createImmutable($prodCfg);

// In some forgotten test fixture:
DB::createImmutable($testCfg);  // ← silently swapped prod for test
```

Now the second call throws. If you truly need to swap, use `replaceImmutable()` — the explicit name is the whole point.

Continue with [Architecture](11-architecture.md).
