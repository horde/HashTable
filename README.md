# Horde HashTable

Key/Value store abstraction with driver-specific capabilities for Redis native
data structures, cross-process locking and transparent serialization of mixed
PHP values.

## Interfaces

| Interface | Capability |
|-----------|-----------|
| `HashTable` | get/set/delete/exists with mixed values and optional TTL |
| `LockableHashTable` | Cross-process lock/unlock (Redis, Memcache) |
| `RedisHashTable` | Atomic counters, hashes, lists, sets, TTL introspection |

## Drivers

| Driver | Implements | Backend |
|--------|-----------|---------|
| `Driver\Redis` | `RedisHashTable` | ext-redis (preferred) or Predis ^3 |
| `Driver\Memcache` | `LockableHashTable` | `Horde\Memcache\MemcacheApi` |
| `Driver\Memory` | `HashTable` | In-process array (testing/development) |
| `Driver\NullDriver` | `HashTable` | No-op (always misses) |

## Installation

```bash
composer require horde/hashtable
```

For Redis (recommended):
```bash
# Preferred: C extension
pecl install redis

# Fallback: pure PHP
composer require predis/predis:^3
```

## Usage

```php
use Horde\HashTable\Driver\Redis;
use Redis as PhpRedis;

$client = new PhpRedis();
$client->connect('127.0.0.1', 6379);

$ht = new Redis(client: $client, prefix: 'myapp_');

// Basic key-value
$ht->set('user:1', ['name' => 'Alice'], ttl: 3600);
$data = $ht->get('user:1');  // returns array or null

// Locking
$ht->lock('user:1');
try {
    $ht->set('user:1', ['name' => 'Bob']);
} finally {
    $ht->unlock('user:1');
}

// Redis-native structures
$ht->increment('counter');
$ht->listPush('queue', $job);
$ht->hashSet('session:abc', 'last_access', time());
$ht->setAdd('tags:post:1', 'php', 'redis');
```

## Horde Core Integration

When used inside a Horde application, you can obtain instances via dependency injection:

```php
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\HashTable\RedisHashTable;

// Any configured driver
$ht = $injector->get(HashTable::class);

// Only drivers with real cross-process locking
$ht = $injector->get(LockableHashTable::class);

// Full Redis power (counters, hashes, lists, sets)
$ht = $injector->get(RedisHashTable::class);
```

Configuration is read from `conf.php` under the `hashtable` key by
`Horde\Core\Factory\HashTableFactory`.

## Legacy API

The PSR-0 classes under `lib/` (`Horde_HashTable_Base`, `Horde_HashTable_Predis`,
etc.) remain available for backward compatibility. New code should use the PSR-4
interfaces in `src/`. See [doc/UPGRADING.md](doc/UPGRADING.md) for migration
details.

## License

LGPL-2.1-only — see [LICENSE](LICENSE).
