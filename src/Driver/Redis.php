<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  HashTable
 */

namespace Horde\HashTable\Driver;

use Horde\HashTable\ConnectionException;
use Horde\HashTable\HashTableException;
use Horde\HashTable\LockTimeoutException;
use Horde\HashTable\LoggingTrait;
use Horde\HashTable\MissTrackingTrait;
use Horde\HashTable\PrefixTrait;
use Horde\HashTable\RedisHashTable;
use Horde\HashTable\SerializationTrait;
use Predis\ClientInterface as PredisClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redis as PhpRedis;

/**
 * Redis-backed HashTable implementation.
 *
 * Prefers ext-redis (PhpRedis) for performance, falls back to predis/predis.
 * Implements the full RedisHashTable including native data structures.
 *
 * For generic set/get with mixed values: serializes transparently.
 * For Redis-native operations (hash, list, set, counter): values pass through
 * as strings (caller is responsible for the data format in native ops).
 */
final class Redis implements RedisHashTable
{
    use PrefixTrait;
    use MissTrackingTrait;
    use LoggingTrait;
    use SerializationTrait;

    private const LOCK_SUFFIX = ':lock';
    private const LOCK_TIMEOUT = 30;
    private const LOCK_RETRY_LIMIT = 300;

    private readonly bool $isPhpRedis;

    /** @var array<string, true> */
    private array $locks = [];

    public function __construct(
        private readonly PhpRedis|PredisClientInterface $client,
        private readonly string $prefix = 'hht_',
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $lockTimeout = self::LOCK_TIMEOUT,
    ) {
        $this->isPhpRedis = $client instanceof PhpRedis;
    }

    public function __destruct()
    {
        foreach (array_keys($this->locks) as $key) {
            $this->unlock($key);
        }
    }

    // --- HashTable: Core KV ---

    public function get(string $key): mixed
    {
        if ($this->isMiss($key)) {
            $this->logMissed($key);
            return null;
        }

        $result = $this->client->get($this->hkey($key));

        if ($result === false || $result === null) {
            $this->recordMiss($key);
            $this->logMissed($key);
            return null;
        }

        $this->logRetrieved($key);
        return $this->deserializeValue($result);
    }

    public function getMultiple(array $keys): array
    {
        $partition = $this->partitionByMissCache($keys);
        $output = array_fill_keys($partition['missed'], null);

        if (empty($partition['pending'])) {
            if ($partition['missed']) {
                $this->logMissed($partition['missed']);
            }
            return $output;
        }

        $storageKeys = [];
        foreach ($partition['pending'] as $key) {
            $storageKeys[] = $this->hkey($key);
        }

        $values = $this->client->mget($storageKeys);
        if ($values === false) {
            $values = array_fill(0, count($storageKeys), false);
        }

        $hits = [];
        $misses = $partition['missed'];

        foreach ($partition['pending'] as $i => $key) {
            $val = $values[$i] ?? false;
            if ($val === false || $val === null) {
                $output[$key] = null;
                $this->recordMiss($key);
                $misses[] = $key;
            } else {
                $output[$key] = $this->deserializeValue($val);
                $hits[] = $key;
            }
        }

        if ($hits) {
            $this->logRetrieved($hits);
        }
        if ($misses) {
            $this->logMissed($misses);
        }

        return $output;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $hk = $this->hkey($key);
        $serialized = $this->serializeValue($value);

        if ($ttl !== null) {
            $result = $this->phpRedisSetex($hk, $ttl, $serialized);
        } else {
            $result = $this->client->set($hk, $serialized);
        }

        $success = $result !== false;
        if ($success) {
            $this->clearMiss($key);
        }
        $this->logSet($key, $success);

        if (!$success) {
            throw new HashTableException("Failed to set key: $key");
        }
    }

    public function replace(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->isMiss($key)) {
            return false;
        }

        $hk = $this->hkey($key);
        if (!$this->clientExists($hk)) {
            $this->recordMiss($key);
            return false;
        }

        $serialized = $this->serializeValue($value);
        $this->client->set($hk, $serialized);
        if ($ttl !== null) {
            $this->client->expire($hk, $ttl);
        }
        $this->clearMiss($key);
        $this->logSet($key, true);
        return true;
    }

    public function delete(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $storageKeys = [];
        foreach ($keys as $key) {
            $storageKeys[] = $this->hkey($key);
            $this->recordMiss($key);
        }

        if ($storageKeys) {
            $this->client->del($storageKeys);
            $this->logDeleted($keys);
        }
    }

    public function exists(string $key): bool
    {
        if ($this->isMiss($key)) {
            return false;
        }

        $result = $this->clientExists($this->hkey($key));
        if (!$result) {
            $this->recordMiss($key);
        }
        return $result;
    }

    public function existsMultiple(array $keys): array
    {
        $partition = $this->partitionByMissCache($keys);
        $output = array_fill_keys($partition['missed'], false);

        if (empty($partition['pending'])) {
            return $output;
        }

        if ($this->isPhpRedis) {
            $pipe = $this->client->pipeline();
            foreach ($partition['pending'] as $key) {
                $pipe->exists($this->hkey($key));
            }
            $results = $pipe->exec();
            foreach ($partition['pending'] as $i => $key) {
                $exists = (bool) ($results[$i] ?? false);
                $output[$key] = $exists;
                if (!$exists) {
                    $this->recordMiss($key);
                }
            }
        } else {
            foreach ($partition['pending'] as $key) {
                $exists = $this->clientExists($this->hkey($key));
                $output[$key] = $exists;
                if (!$exists) {
                    $this->recordMiss($key);
                }
            }
        }

        return $output;
    }

    public function clear(): void
    {
        $pattern = addcslashes($this->prefix, '?*[]\\') . '*';

        if ($this->isPhpRedis) {
            $iterator = null;
            while ($keys = $this->client->scan($iterator, $pattern, 100)) {
                $this->client->del($keys);
            }
        } else {
            $keys = $this->client->keys($pattern);
            if ($keys) {
                $this->client->del($keys);
            }
        }

        $this->clearAllMisses();
    }

    // --- LockableHashTable ---

    public function lock(string $key): void
    {
        $lockKey = $this->hkey($key) . self::LOCK_SUFFIX;
        $attempts = 0;

        while (!$this->acquireLock($lockKey)) {
            if (++$attempts >= self::LOCK_RETRY_LIMIT) {
                throw new LockTimeoutException(
                    "Failed to acquire lock on key '$key' after $attempts attempts"
                );
            }
            usleep(min(pow(2, $attempts) * 10_000, 100_000));
        }

        $this->client->expire($lockKey, $this->lockTimeout);
        $this->locks[$key] = true;
    }

    public function unlock(string $key): void
    {
        $lockKey = $this->hkey($key) . self::LOCK_SUFFIX;
        $this->client->del([$lockKey]);
        unset($this->locks[$key]);
    }

    // --- RedisHashTable: Atomic Counters ---

    public function increment(string $key, int $by = 1): int
    {
        $hk = $this->hkey($key);
        $this->clearMiss($key);

        if ($by === 1) {
            return (int) $this->client->incr($hk);
        }
        return (int) $this->client->incrBy($hk, $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        $hk = $this->hkey($key);
        $this->clearMiss($key);

        if ($by === 1) {
            return (int) $this->client->decr($hk);
        }
        return (int) $this->client->decrBy($hk, $by);
    }

    // --- RedisHashTable: TTL ---

    public function ttl(string $key): ?int
    {
        $result = $this->client->ttl($this->hkey($key));

        // Redis returns -2 if key doesn't exist, -1 if no expiry
        if ($result === -2 || $result === false) {
            return null;
        }
        if ($result === -1) {
            return null;
        }
        return (int) $result;
    }

    // --- RedisHashTable: Hash Operations ---

    public function hashGet(string $key, string $field): mixed
    {
        $result = $this->client->hGet($this->hkey($key), $field);
        if ($result === false || $result === null) {
            return null;
        }
        return $result;
    }

    public function hashSet(string $key, string $field, mixed $value): void
    {
        $this->client->hSet($this->hkey($key), $field, $value);
        $this->clearMiss($key);
    }

    public function hashGetAll(string $key): array
    {
        $result = $this->client->hGetAll($this->hkey($key));
        if ($result === false || $result === null) {
            return [];
        }
        return (array) $result;
    }

    public function hashDelete(string $key, string|array $fields): void
    {
        $fields = is_array($fields) ? $fields : [$fields];
        $hk = $this->hkey($key);

        if ($this->isPhpRedis) {
            $this->client->hDel($hk, ...$fields);
        } else {
            foreach ($fields as $field) {
                $this->client->hDel($hk, $field);
            }
        }
    }

    // --- RedisHashTable: List Operations ---

    public function listPush(string $key, mixed $value): int
    {
        $this->clearMiss($key);
        return (int) $this->client->rPush($this->hkey($key), $value);
    }

    public function listPop(string $key): mixed
    {
        $result = $this->client->lPop($this->hkey($key));
        if ($result === false || $result === null) {
            return null;
        }
        return $result;
    }

    public function listRange(string $key, int $start = 0, int $stop = -1): array
    {
        $result = $this->client->lRange($this->hkey($key), $start, $stop);
        if ($result === false || $result === null) {
            return [];
        }
        return $result;
    }

    // --- RedisHashTable: Set Operations ---

    public function setAdd(string $key, mixed ...$members): int
    {
        $this->clearMiss($key);
        $hk = $this->hkey($key);

        if ($this->isPhpRedis) {
            return (int) $this->client->sAdd($hk, ...$members);
        }

        $count = 0;
        foreach ($members as $member) {
            $count += (int) $this->client->sAdd($hk, $member);
        }
        return $count;
    }

    public function setMembers(string $key): array
    {
        $result = $this->client->sMembers($this->hkey($key));
        if ($result === false || $result === null) {
            return [];
        }
        return $result;
    }

    public function setRemove(string $key, mixed ...$members): int
    {
        $hk = $this->hkey($key);

        if ($this->isPhpRedis) {
            return (int) $this->client->sRem($hk, ...$members);
        }

        $count = 0;
        foreach ($members as $member) {
            $count += (int) $this->client->sRem($hk, $member);
        }
        return $count;
    }

    public function setIsMember(string $key, mixed $member): bool
    {
        return (bool) $this->client->sIsMember($this->hkey($key), $member);
    }

    // --- Private Helpers ---

    private function acquireLock(string $lockKey): bool
    {
        if ($this->isPhpRedis) {
            return (bool) $this->client->setnx($lockKey, '1');
        }

        // Predis setnx returns Status reply or int
        $result = $this->client->setnx($lockKey, '1');
        return (bool) $result;
    }

    private function clientExists(string $storageKey): bool
    {
        $result = $this->client->exists($storageKey);
        // ext-redis returns int (count), predis returns int/bool
        return (bool) $result;
    }

    private function phpRedisSetex(string $hk, int $ttl, string $value): mixed
    {
        if ($this->isPhpRedis) {
            return $this->client->setex($hk, $ttl, $value);
        }
        // Predis: set with EX option
        return $this->client->set($hk, $value, 'EX', $ttl);
    }
}
