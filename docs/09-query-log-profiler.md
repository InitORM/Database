# Query log profiler

The query log is a per-Connection in-memory buffer of every statement executed while logging is enabled. Use it to inspect what SQL your app actually issues, measure query timings, and catch N+1 problems.

## Enabling

```php
$db->enableQueryLog();

$db->read('users', ['id', 'name'], ['active' => 1]);
$db->read('posts', ['id', 'title'], ['user_id' => 5]);

print_r($db->getQueryLogs());
```

Output:

```
Array
(
    [0] => Array
        (
            [query] => SELECT id, name FROM users WHERE active = :active
            [args]  => Array ( [:active] => 1 )
            [timer] => 0.000642
        )

    [1] => Array
        (
            [query] => SELECT id, title FROM posts WHERE user_id = :user_id
            [args]  => Array ( [:user_id] => 5 )
            [timer] => 0.000478
        )
)
```

Each entry contains:

| Key     | Type                              | Meaning                                            |
|---------|-----------------------------------|----------------------------------------------------|
| `query` | `string`                          | The SQL string handed to PDO.                      |
| `args`  | `array<string, mixed>\|null`      | The bound parameter map (may be `null` for raw).   |
| `timer` | `float`                           | Wall-clock seconds, `microtime(true)` difference.  |

## Disabling

```php
$db->enableQueryLog();
$db->read('users');           // recorded

$db->disableQueryLog();
$db->read('users');           // NOT recorded

print_r($db->getQueryLogs()); // only the first read appears
```

Disabling preserves existing entries — it only stops new ones from being appended. To clear the buffer, fetch it once and discard; there is no `clearQueryLogs()` on the Database surface (use `$db->getConnection()->setQueryLogs(false)->setQueryLogs(true)` to reset the underlying QueryLogger).

## Bootstrap-time enabling

Set `queryLogs: true` in the credentials array to have the log running before any code touches the Database:

```php
DB::createImmutable([
    'dsn'       => '…',
    'queryLogs' => true,
]);
```

## Use cases

### Finding N+1 problems

```php
$db->enableQueryLog();
$users = $db->read('users')->asAssoc()->rows();

foreach ($users as $user) {
    $posts = $db->read('posts', ['*'], ['user_id' => $user['id']])->asAssoc()->rows();
    // …
}

$logs = $db->getQueryLogs();
echo count($logs); // 1 + number of users → that's an N+1
```

### Slow-query budget

```php
$slow = array_filter($db->getQueryLogs(), fn($entry) => $entry['timer'] > 0.05);
foreach ($slow as $entry) {
    error_log(sprintf('SLOW: %.3fs %s', $entry['timer'], $entry['query']));
}
```

### Dev dashboard

```php
register_shutdown_function(function () use ($db) {
    if (!headers_sent()) {
        header('X-Query-Count: ' . count($db->getQueryLogs()));
    }
});
```

## Cost

The log lives in process memory and grows unbounded. For long-running CLI workers, drain it periodically:

```php
while ($job = $queue->pop()) {
    $job->handle();

    // Drain to keep memory flat
    if (count($db->getQueryLogs()) > 10_000) {
        $db->getConnection()->setQueryLogs(false)->setQueryLogs(true);
    }
}
```

In production, leave `queryLogs` off unless you're actively profiling — the timing measurement itself is cheap, but unbounded memory growth is not.

Continue with [Facade vs instance](10-facade-vs-instance.md).
