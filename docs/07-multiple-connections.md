# Multiple connections

The static facade is intentionally a *single* connection — most applications only need one. When you need more (replicas, sharded data, a reporting warehouse, a tenant database, …) there are two patterns.

## Pattern 1 — facade + secondary instances

Keep the facade for your primary database; build secondary Databases with `DB::connect()` or `new Database(...)`. Neither touches the facade slot.

```php
use InitORM\Database\Database;
use InitORM\Database\Facade\DB;

// Primary, used everywhere via DB::…
DB::createImmutable([
    'dsn'      => 'mysql:host=primary.internal;dbname=app;charset=utf8mb4',
    'username' => 'app',
    'password' => '…',
]);

// Secondary read-replica, passed around by reference
$replica = DB::connect([
    'dsn'      => 'mysql:host=replica.internal;dbname=app;charset=utf8mb4',
    'username' => 'app_ro',
    'password' => '…',
]);

$users = $replica->read('users')->asAssoc()->rows();
```

`DB::connect()` is a thin alias of `new Database(...)` — use whichever reads better in your codebase.

## Pattern 2 — pure instance API, no facade

Skip the facade entirely and pass `DatabaseInterface` around via dependency injection. This is the recommended setup for libraries, microservices, and applications with DI containers.

```php
use InitORM\Database\Database;
use InitORM\Database\Interfaces\DatabaseInterface;

final class UserRepository
{
    public function __construct(private DatabaseInterface $db) {}

    public function findActive(): array
    {
        return $this->db->read('users', ['*'], ['active' => 1])->asAssoc()->rows();
    }
}

$primary = new Database([...]);
$repo    = new UserRepository($primary);
```

## Sharing a connection between sibling Databases

Sometimes you want two Databases that share a live connection (so they're in the same transaction context) but carry independent builder state. Use `withFreshBuilder()`:

```php
$base    = new Database([...]);
$sibling = $base->withFreshBuilder();

self::assertSame($base->getConnection(), $sibling->getConnection());

$base->select('id')->where('active', '=', 1);   // base has builder state
$sibling->read('users');                         // sibling is clean: SELECT * FROM users
```

Useful when composing a sub-query against the same live transaction without polluting the parent builder.

## Swapping the facade target

`createImmutable()` is single-shot — calling it twice throws to prevent silent overrides. When you genuinely need to swap (typically: between tests), use `replaceImmutable()`:

```php
// In a test setUp:
DB::replaceImmutable(SqliteHelper::makeConnection());

// To clear the facade entirely (e.g. in tearDown):
DB::replaceImmutable(null);
```

`replaceImmutable()` accepts a credentials array, a `ConnectionInterface`, a `DatabaseInterface`, or `null`.

## Pooling

Neither this package nor `initorm/dbal` pool connections — PDO is created on demand and held for the lifetime of the Connection object. If you need connection pooling, run it in a layer above (e.g. PHP-FPM keeps PDO connections alive within a request; for persistent across-requests pooling, set `PDO::ATTR_PERSISTENT => true` in `options`).

Continue with [Logger and debug](08-logger-and-debug.md).
