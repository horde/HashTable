# Upgrading Horde HashTable

## From 1.x to 2.x (PSR-4 Modern API)

Version 2.x introduces a fully new PSR-4 interface layer in `src/` alongside
the existing PSR-0 classes in `lib/`. The legacy API is preserved for the time being but discouraged. No existing
code breaks on upgrade. New code should target the modern interfaces.

### New Interfaces

Replace references to `Horde_HashTable_Base` with the appropriate interface:

| Old (PSR-0) | New (PSR-4) |
|-------------|-------------|
| `Horde_HashTable_Base` | `Horde\HashTable\HashTable` |
| `Horde_HashTable_Base` + `Horde_HashTable_Lock` | `Horde\HashTable\LockableHashTable` |
| *(no equivalent)* | `Horde\HashTable\RedisHashTable` |

### API Changes

#### Miss Sentinel

```php
// Old: returns false on miss
$val = $old->get($key);
if ($val === false) { /* miss */ }

// New: returns null on miss
$val = $new->get($key);
if ($val === null) { /* miss */ }
```

#### Multi-Get

```php
// Old: get() accepts array
$results = $old->get(['key1', 'key2']);

// New: dedicated getMultiple()
$results = $new->getMultiple(['key1', 'key2']);
```

#### Set with TTL

```php
// Old: options array
$old->set($key, $value, ['expire' => 3600]);

// New: direct parameter, null = no expiration
$new->set($key, $value, 3600);
$new->set($key, $value);        // no expiration
$new->set($key, $value, null);  // explicit no expiration
```

#### Delete

```php
// Old: returns bool
$success = $old->delete(['key1', 'key2']);

// New: returns void, throws on hard failure
$new->delete(['key1', 'key2']);
```

#### Locking

```php
// Old: interface intersection
function needsLock(Horde_HashTable_Base&Horde_HashTable_Lock $ht) { ... }

// New: single interface
function needsLock(LockableHashTable $ht) { ... }
```

### Driver Changes

| Old Driver Class | New Driver Class | Notes |
|-----------------|-----------------|-------|
| `Horde_HashTable_Predis` | `Horde\HashTable\Driver\Redis` | Supports both ext-redis and Predis ^3 |
| `Horde_HashTable_Memcache` | `Horde\HashTable\Driver\Memcache` | Takes `MemcacheApi` directly |
| *(no equivalent)* | `Horde\HashTable\Driver\Memory` | In-process, for testing |
| *(no equivalent)* | `Horde\HashTable\Driver\NullDriver` | No-op |
| `Horde_HashTable_Vfs` | *(not carried forward)* | See below |

### VFS Driver Removed

`Horde_HashTable_Vfs` is not present in the modern API. It was a filesystem
fallback with no locking, no expiration and poor performance. Its only real
consumer is `Horde_Core_HashTable_PersistentSession` (a session-scoped temp
file manager in IMP), which should be replaced by a dedicated `TempFileStore`
service.

### Dependency Injection (Horde Core)

The legacy DI key `'Horde_HashTable'` continues to work unchanged. New code
should use the interface class names:

```php
// Old
$ht = $injector->getInstance('Horde_HashTable');

// New
use Horde\HashTable\HashTable;
$ht = $injector->get(HashTable::class);
```

Both bindings coexist. The legacy factory (`Horde_Core_Factory_HashTable`)
and the modern factory (`Horde\Core\Factory\HashTableFactory`) read the same
`conf.php` configuration under the `hashtable` key.

### Configuration

No changes to `conf.php` are required. The modern factory reads the same keys:

```php
$conf['hashtable']['driver'] = 'predis';  // or 'memcache', 'memory'
$conf['hashtable']['params']['hostspec'] = ['127.0.0.1'];
$conf['hashtable']['params']['port'] = [6379];
$conf['hashtable']['params']['password'] = null;
$conf['hashtable']['params']['database'] = 0;
$conf['hashtable']['params']['protocol'] = 'tcp';  // or 'unix'
$conf['hashtable']['params']['socket'] = '/var/run/redis.sock';
```

The modern factory additionally supports:

```php
$conf['hashtable']['prefix'] = 'hht_';       // key prefix (default: 'hht_')
$conf['hashtable']['lock_timeout'] = 30;     // lock timeout in seconds
```

When ext-redis is loaded it is used automatically; Predis is the fallback
regardless of whether the driver name is `'predis'` or `'redis'`.

### Composer

The package constraint `"horde/hashtable": "^2 || dev-FRAMEWORK_6_0"` covers
both old and new code. The PSR-4 autoload entry was added in 2.0.0-beta1.

### Exceptions

| Old | New |
|-----|-----|
| `Horde_HashTable_Exception` | `Horde\HashTable\HashTableException` |
| *(no equivalent)* | `Horde\HashTable\ConnectionException` |
| *(no equivalent)* | `Horde\HashTable\LockTimeoutException` |

All new exceptions extend `HashTableException` which implements
`Horde\Exception\HordeThrowable`.
