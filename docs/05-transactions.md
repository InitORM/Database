# Transactions

The Database exposes a single high-level transaction entry point:

```php
$db->transaction(Closure $closure, int $attempt = 1, bool $testMode = false): bool;
```

The closure receives the Database instance as its only argument. The transaction is committed when the closure returns; it is rolled back when the closure throws.

## Commit on success

```php
$db->transaction(function ($db) {
    $orderId = $db->create('orders', ['user_id' => 5, 'total' => 199.90])
                  ? $db->insertId()
                  : null;

    $db->create('order_items', [
        'order_id' => $orderId,
        'sku'      => 'X-1',
        'qty'      => 1,
    ]);
});
```

If both inserts succeed, the transaction commits and the helper returns `true`.

## Rollback on exception

When the closure throws, the current transaction is rolled back and the original throwable is wrapped in `DatabaseException`:

```php
use InitORM\Database\Exceptions\DatabaseException;

try {
    $db->transaction(function ($db) {
        $db->create('orders', [...]);
        throw new \RuntimeException('payment declined');
    });
} catch (DatabaseException $e) {
    // $e->getMessage()    => "Transaction failed after 1 attempt(s): payment declined"
    // $e->getPrevious()   => the original RuntimeException
}
```

This was a regression in earlier versions: failures used to be swallowed and the caller got back a bare `false`. Now you always get the original error — wrapped, but reachable through `getPrevious()`.

## Retry on transient failures

Set `$attempt > 1` to retry the closure on failure (each attempt opens a fresh transaction):

```php
$db->transaction(function ($db) {
    // Some operation that might deadlock under contention.
    $db->update('counters', ['value' => $db->raw('value + 1')], ['id' => 1]);
}, attempt: 5);
```

If any attempt succeeds, the helper returns `true`. If all attempts fail, the **last** error is wrapped and re-thrown.

`attempt < 1` throws `DatabaseInvalidArgumentException` — silently doing nothing is worse than a loud failure.

## testMode: roll back even on success

Pass `testMode: true` to always roll back. Useful for integration tests that want to exercise the closure without persisting changes:

```php
$db->transaction(function ($db) {
    $db->create('users', ['name' => 'TEMP', 'email' => 't@example.com', 'active' => 1]);
}, testMode: true);

// The INSERT was rolled back — table is unchanged.
```

## Nested transactions

PDO does not natively support nested transactions, so attempting to start one inside another throws:

```php
$db->transaction(function ($db) {
    $db->transaction(function ($db) {
        // Throws: "Cannot start a transaction while another is already in progress."
    });
});
```

If you need savepoints, drop down to raw SQL:

```php
$db->transaction(function ($db) {
    $db->query('SAVEPOINT sp_1');
    try {
        $db->create('audit', [...]);
    } catch (\Throwable $e) {
        $db->query('ROLLBACK TO sp_1');
        throw $e;
    }
    $db->query('RELEASE SAVEPOINT sp_1');
});
```

## Caveats

- **DDL inside a transaction** (e.g. `CREATE TABLE`) causes MySQL to implicit-commit. The helper guards `rollBack()` with `inTransaction()`, but you should still avoid mixing DDL into a retried transaction.
- **The closure receives this Database**, not a fresh sibling. Builder state from before the transaction is preserved; CRUD calls inside the closure reset state as usual.
- **No automatic savepoints.** If you need finer-grained partial rollback, use raw `SAVEPOINT` SQL as shown above.

Continue with [Raw queries](06-raw-queries.md).
