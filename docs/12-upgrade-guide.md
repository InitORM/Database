# Upgrade guide — v2 → v3

v3 fixes a handful of long-standing bugs and tightens the public API. Most applications will upgrade with no code changes; a few patterns need touching.

## TL;DR

```bash
composer require initorm/database:^3.0
```

Then review the breaking-changes list below.

## Breaking changes

### 1. PHP version

| v2     | v3     |
|--------|--------|
| `>=7.4` (in composer.json — actually broken because the dependencies require 8.0+) | `^8.1` |

v3 honestly requires PHP 8.1 — matching the actual constraints of `initorm/query-builder ^2.0`.

### 2. `DB::createImmutable()` is now actually immutable

A second call throws `DatabaseException`. If you really want to swap, use the new explicit `replaceImmutable()`:

```diff
- DB::createImmutable($newCfg);  // silently overrode previous
+ DB::replaceImmutable($newCfg); // explicit swap
```

`createImmutable()` is the safe default; `replaceImmutable()` is the escape hatch.

### 3. CRUD return semantics

`create()` / `createBatch()` / `update()` / `updateBatch()` / `delete()` now return `bool true` on successful execution (and throw on failure). They no longer return `numRows() > 0` — which used to be misleading when an UPDATE found rows but didn't change any values.

```diff
  // Don't do this anymore — it's always true when execution succeeded:
- if ($db->update('users', $data, ['id' => 1])) {
-     $ok = true;
- }

  // Do one of these:
+ try {
+     $db->update('users', $data, ['id' => 1]);
+     $changed = $db->affectedRows();   // 0 = matched-no-change OR no match
+ } catch (\InitORM\Database\Exceptions\DatabaseException $e) {
+     // execution failure
+ }
```

`insertId()` still returns the inserted id (now typed `string|false`).

### 4. `insertId()` return type

| v2                       | v3              |
|--------------------------|-----------------|
| Untyped, returned PDO's `string\|false` | Typed `string\|false` |

The interface and facade `@method` annotation used to claim `int|string|false`, which didn't match the implementation. Now everything agrees on `string|false` — matching `\PDO::lastInsertId()`.

If you have code like `$id = (int) $db->insertId()`, it still works.

### 5. `transaction()` propagates exceptions

Exceptions thrown inside the closure are no longer swallowed. The original throwable is reachable via `getPrevious()`:

```diff
- $ok = $db->transaction(function () { ... });
- if (!$ok) {
-     // ??? — no way to know what failed
- }

+ try {
+     $db->transaction(function () { ... });
+ } catch (\InitORM\Database\Exceptions\DatabaseException $e) {
+     $original = $e->getPrevious();
+     // handle $original
+ }
```

`attempt: 0` now also throws (`DatabaseInvalidArgumentException`) instead of silently doing nothing.

### 6. Builder state is reset after every CRUD call

In v2, this would silently bleed:

```php
DB::where('id', '=', 1)->delete('users');
$rows = DB::read('users')->asAssoc()->rows(); // returned 0 rows in v2 — WHERE leaked
```

In v3, the structure and parameter bag are wiped in a `finally` block after every CRUD execution, so the second read correctly returns all remaining users.

If you relied on the old leaking behaviour (please don't), explicitly re-chain the clauses.

### 7. `DatabaseInterface` no longer declares `__construct`

Constructor signatures on interfaces are LSP-hostile. The interface now only declares behavioural methods; concrete implementations are free to define their own constructor signatures.

If you implemented `DatabaseInterface` yourself, your constructor no longer needs to match the abstract signature.

### 8. `DB::__call` is removed

`DB` is now a strictly static facade — instantiation throws (the constructor is `private`). If you had `(new DB())->...` anywhere, switch to the static form `DB::...`.

### 9. Renamed: `Database::builder()` → `Database::withFreshBuilder()`

The old name was ambiguous — `builder` could mean "the inner builder" or "a new sibling Database". The new name says what it does. The old `builder()` is kept as a deprecated thin alias for the duration of the v3 line.

## Non-breaking improvements

- **PHPStan level 6** clean.
- **PSR-12** clean (PHPCS in CI).
- **49 unit tests / 90% line coverage.**
- **CI workflows** (`phpunit`, `phpcs`, `phpstan`, `composer-validate`) for PHP 8.1–8.4.
- **`affectedRows()`** new method — returns the rowCount of the most recent CRUD call.
- **`__clone()`** properly deep-copies the inner builder.
- **`QueryBuilderFactoryInterface`** can be injected into the constructor — useful for tests.
- **All exceptions carry descriptive messages.**
- **All `@method` annotations on the facade** match the actual QueryBuilder signatures (a handful were stale in v2).
- **README + `docs/`** rewritten end-to-end.

## Migration cheatsheet

| If you do this in v2…                              | …do this in v3                                       |
|-----------------------------------------------------|------------------------------------------------------|
| `if ($db->create(...)) { … }`                       | Same — still works, true == success                  |
| `if (!$db->update(...)) { return false; }`          | Wrap in `try/catch` and inspect `affectedRows()`     |
| `$db->transaction($fn)` and check the return       | `try { $db->transaction($fn) } catch (DatabaseException $e)` |
| `DB::createImmutable(...)` repeatedly in tests      | Call `DB::replaceImmutable(...)` in `setUp()`        |
| `(new DB())->...`                                   | `DB::...`                                            |
| `$db->builder()`                                    | `$db->withFreshBuilder()` (old name still works)     |
| Implement `DatabaseInterface` with own constructor  | Just remove `__construct` from interface usage       |

Questions, surprises, or migration friction? Open an issue at <https://github.com/InitORM/Database/issues>.
